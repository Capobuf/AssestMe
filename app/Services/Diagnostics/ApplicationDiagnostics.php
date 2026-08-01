<?php

declare(strict_types=1);

namespace App\Services\Diagnostics;

use App\Data\Database\DatabaseIntegrityResult;
use App\Data\Diagnostics\ApplicationDiagnosticReport;
use App\Data\Diagnostics\DiagnosticCheckData;
use App\Data\Diagnostics\DiagnosticCheckStatus;
use App\Services\Backups\LatestBackupStatus;
use App\Services\Database\DatabaseClientBinaryResolver;
use App\Services\Database\DatabaseDriverResolver;
use App\Services\Installation\InstallationRuntimeInspector;
use App\Services\Installation\SchedulerHeartbeat;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class ApplicationDiagnostics
{
    /** @var list<string> */
    private const COMMON_EXTENSIONS = [
        'bcmath',
        'ctype',
        'curl',
        'dom',
        'fileinfo',
        'filter',
        'gd',
        'iconv',
        'intl',
        'libxml',
        'mbstring',
        'openssl',
        'pdo',
        'phar',
        'session',
        'simplexml',
        'tokenizer',
        'xml',
        'xmlreader',
        'xmlwriter',
        'zip',
        'zlib',
    ];

    public function __construct(
        private DatabaseDriverResolver $databaseDriverResolver,
        private DatabaseClientBinaryResolver $databaseClientBinaryResolver,
        private InstallationRuntimeInspector $runtimeInspector,
        private SchedulerHeartbeat $schedulerHeartbeat,
        private LatestBackupStatus $latestBackupStatus,
        private Migrator $migrator,
    ) {}

    public function run(?Connection $connection = null): ApplicationDiagnosticReport
    {
        $missingExtensions = array_values(array_filter(
            self::COMMON_EXTENSIONS,
            static fn (string $extension): bool => ! extension_loaded($extension),
        ));
        $checks = $this->runtimeChecks($missingExtensions);
        $checks[] = $this->writablePathsCheck();
        $checks[] = $this->productionConfigurationCheck();
        $checks[] = $this->schedulerCheck();
        $checks[] = $this->backupCheck();

        $driver = 'unavailable';
        $product = null;
        $serverVersion = null;

        try {
            $connection ??= DB::connection();
            $connection->getPdo();
            $driver = $connection->getDriverName();
            $checks[] = $this->passed('database_connection', 'database', $connection->getName());
        } catch (Throwable) {
            $checks[] = $this->failed(
                'database_connection',
                'database',
                'La connessione al database non è disponibile.',
            );
            $checks[] = $this->failed(
                'single_administrator',
                'common',
                'Il numero di amministratori non può essere verificato.',
            );

            return new ApplicationDiagnosticReport(
                phpVersion: PHP_VERSION,
                memoryLimit: (string) ini_get('memory_limit'),
                missingExtensions: $missingExtensions,
                driver: $driver,
                product: $product,
                serverVersion: $serverVersion,
                checks: $checks,
            );
        }

        $checks[] = $this->administratorCheck($connection);
        $checks[] = $this->migrationCheck();

        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            $checks[] = $this->failed(
                'database_driver',
                'database',
                "Driver non supportato: {$driver}.",
            );

            return new ApplicationDiagnosticReport(
                phpVersion: PHP_VERSION,
                memoryLimit: (string) ini_get('memory_limit'),
                missingExtensions: $missingExtensions,
                driver: $driver,
                product: null,
                serverVersion: null,
                checks: $checks,
            );
        }

        $checks[] = $this->passed('database_driver', 'database', $driver);
        $checks[] = $this->databaseExtensionCheck($driver);

        if ($driver === 'sqlite') {
            $checks[] = $this->sqlitePathCheck($connection);
        } else {
            $checks[] = $this->databaseUtilitiesCheck($driver);
        }

        try {
            $integrity = $this->databaseDriverResolver
                ->integrityChecker($connection)
                ->check($connection);
            $product = $integrity->product;
            $serverVersion = $integrity->serverVersion;
            $checks = [
                ...$checks,
                ...$this->successfulIntegrityChecks($integrity),
            ];
        } catch (Throwable) {
            $checks[] = $this->failed(
                'database_integrity',
                'database',
                "Il controllo di integrità {$driver} non è riuscito.",
            );
            $checks = [
                ...$checks,
                ...$this->unavailableIntegrityChecks($driver),
            ];
        }

        return new ApplicationDiagnosticReport(
            phpVersion: PHP_VERSION,
            memoryLimit: (string) ini_get('memory_limit'),
            missingExtensions: $missingExtensions,
            driver: $driver,
            product: $product,
            serverVersion: $serverVersion,
            checks: $checks,
        );
    }

    /**
     * @param  list<string>  $missingExtensions
     * @return list<DiagnosticCheckData>
     */
    private function runtimeChecks(array $missingExtensions): array
    {
        $versionPassed = version_compare(PHP_VERSION, '8.3.0', '>=');
        $memoryLimit = trim((string) ini_get('memory_limit'));
        $memoryBytes = ini_parse_quantity($memoryLimit);
        $memoryPassed = $memoryLimit === '-1' || $memoryBytes >= 512 * 1024 * 1024;
        $checks = [
            $versionPassed
                ? $this->passed('php_version', 'common', PHP_VERSION)
                : $this->failed('php_version', 'common', PHP_VERSION),
            $memoryPassed
                ? $this->passed('memory_limit', 'common', $memoryLimit)
                : $this->failed('memory_limit', 'common', $memoryLimit),
            $missingExtensions === []
                ? $this->passed('common_extensions', 'common', implode(', ', self::COMMON_EXTENSIONS))
                : $this->failed('common_extensions', 'common', implode(', ', $missingExtensions)),
        ];

        try {
            $phpBinary = config('assestme.installation.php_binary');
            $weasyPrintBinary = config('laravel-pdf.weasyprint.binary');
            $inspection = $this->runtimeInspector->inspect(
                base_path(),
                is_string($phpBinary) ? $phpBinary : null,
                is_string($weasyPrintBinary) ? $weasyPrintBinary : null,
            );
            $phpCli = $inspection->requirement('runtime.php_cli');
            $weasyPrint = $inspection->requirement('runtime.weasyprint');

            $checks[] = $phpCli !== null && $phpCli->passed
                ? $this->passed('php_cli', 'common', $phpCli->actual)
                : $this->failed('php_cli', 'common', $phpCli === null ? 'non disponibile' : $phpCli->actual);
            $checks[] = $weasyPrint !== null && $weasyPrint->passed
                ? $this->passed('weasyprint', 'common', $weasyPrint->actual)
                : $this->failed('weasyprint', 'common', $weasyPrint === null ? 'non disponibile' : $weasyPrint->actual);
        } catch (Throwable) {
            $checks[] = $this->failed('php_cli', 'common', 'Verifica PHP CLI non riuscita.');
            $checks[] = $this->failed('weasyprint', 'common', 'Verifica PDF WeasyPrint non riuscita.');
        }

        return $checks;
    }

    private function writablePathsCheck(): DiagnosticCheckData
    {
        $paths = [
            storage_path(),
            storage_path('app/private'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];
        $invalid = array_values(array_filter(
            $paths,
            static fn (string $path): bool => is_link($path)
                || ! is_dir($path)
                || ! is_readable($path)
                || ! is_writable($path),
        ));

        return $invalid === []
            ? $this->passed('writable_paths', 'common', (string) count($paths))
            : $this->failed('writable_paths', 'common', implode(', ', $invalid));
    }

    private function productionConfigurationCheck(): DiagnosticCheckData
    {
        if (! app()->environment('production')) {
            return $this->warning(
                'production_configuration',
                'common',
                'Controllo non bloccante fuori dall’ambiente production.',
            );
        }

        $applicationUrl = config('app.url');
        $valid = is_string($applicationUrl)
            && str_starts_with($applicationUrl, 'https://')
            && config('app.debug') === false
            && config('app.locale') === 'it'
            && config('app.fallback_locale') === 'it'
            && config('app.timezone') === 'Europe/Rome'
            && config('cache.default') === 'file'
            && config('session.driver') === 'file'
            && config('session.encrypt') === true
            && config('queue.default') === 'sync'
            && config('filesystems.default') === 'local'
            && config('laravel-pdf.driver') === 'weasyprint';

        return $valid
            ? $this->passed('production_configuration', 'common', 'production')
            : $this->failed(
                'production_configuration',
                'common',
                'Una o più invarianti di produzione non sono rispettate.',
            );
    }

    private function schedulerCheck(): DiagnosticCheckData
    {
        $heartbeat = $this->schedulerHeartbeat->status();
        $detail = $heartbeat->lastRunAt?->toIso8601String() ?? $heartbeat->status;

        return match ($heartbeat->status) {
            'verified' => $this->passed('scheduler_heartbeat', 'common', $detail),
            'pending' => $this->warning('scheduler_heartbeat', 'common', $detail),
            default => $this->failed('scheduler_heartbeat', 'common', $detail),
        };
    }

    private function backupCheck(): DiagnosticCheckData
    {
        $root = config('assestme.backup.root');

        if (! is_string($root)
            || ! str_starts_with($root, DIRECTORY_SEPARATOR)
            || is_link($root)
            || ! is_dir($root)
            || ! is_readable($root)
            || ! is_writable($root)) {
            return $this->failed('backup_status', 'common', 'Directory backup non disponibile.');
        }

        try {
            $latest = $this->latestBackupStatus->latestSuccessfulAt();
        } catch (Throwable) {
            return $this->failed('backup_status', 'common', 'Catalogo backup non leggibile.');
        }

        return $latest === null
            ? $this->warning('backup_status', 'common', 'Nessun backup applicativo disponibile.')
            : $this->passed('backup_status', 'common', $latest->toIso8601String());
    }

    private function administratorCheck(Connection $connection): DiagnosticCheckData
    {
        try {
            $count = $connection->table('users')->count();
        } catch (Throwable) {
            return $this->failed(
                'single_administrator',
                'common',
                'Il numero di amministratori non può essere verificato.',
            );
        }

        return $count === 1
            ? $this->passed('single_administrator', 'common', '1')
            : $this->failed('single_administrator', 'common', (string) $count);
    }

    private function migrationCheck(): DiagnosticCheckData
    {
        try {
            $paths = array_values(array_unique([database_path('migrations'), ...$this->migrator->paths()]));
            $files = $this->migrator->getMigrationFiles($paths);
            $pending = array_values(array_diff(array_keys($files), $this->migrator->getRepository()->getRan()));
        } catch (Throwable) {
            return $this->failed('database_migrations', 'database', 'Stato migration non disponibile.');
        }

        return $pending === []
            ? $this->passed('database_migrations', 'database', '0')
            : $this->failed('database_migrations', 'database', implode(', ', $pending));
    }

    private function databaseExtensionCheck(string $driver): DiagnosticCheckData
    {
        $extension = $driver === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql';

        return extension_loaded($extension)
            ? $this->passed('database_extension', 'database', $extension)
            : $this->failed('database_extension', 'database', $extension);
    }

    private function sqlitePathCheck(Connection $connection): DiagnosticCheckData
    {
        $path = $connection->getDatabaseName();
        $public = realpath(public_path());
        $resolved = realpath($path);
        $outsidePublic = is_string($public)
            && is_string($resolved)
            && $resolved !== $public
            && ! str_starts_with($resolved, $public.DIRECTORY_SEPARATOR);
        $valid = str_starts_with($path, DIRECTORY_SEPARATOR)
            && $path !== ':memory:'
            && ! is_link($path)
            && is_file($path)
            && is_readable($path)
            && is_writable($path)
            && $outsidePublic;

        return $valid
            ? $this->passed('sqlite_path', 'sqlite', $resolved ?: $path)
            : $this->failed('sqlite_path', 'sqlite', 'Percorso SQLite non sicuro o non disponibile.');
    }

    private function databaseUtilitiesCheck(string $driver): DiagnosticCheckData
    {
        try {
            $dumpBinary = $this->databaseClientBinaryResolver->resolveDump($driver);
            $restoreBinary = $this->databaseClientBinaryResolver->resolveRestore($driver);
        } catch (Throwable) {
            return $this->failed(
                'database_utilities',
                $driver,
                'Il rilevamento dei client database non è riuscito.',
            );
        }

        if ($dumpBinary === null || $restoreBinary === null) {
            $detail = match (true) {
                $dumpBinary === null && $driver === 'mariadb' => 'Backup non disponibile: installare mariadb-client. Restore sicuro non disponibile senza safety backup.',
                $dumpBinary === null => 'Backup non disponibile: installare un client MySQL che fornisca mysqldump. Restore sicuro non disponibile senza safety backup.',
                $driver === 'mariadb' => 'Backup disponibile; restore non disponibile: installare mariadb-client per fornire mariadb.',
                default => 'Backup disponibile; restore non disponibile: installare un client MySQL che fornisca mysql.',
            };

            return $this->warning('database_utilities', $driver, $detail);
        }

        return $this->passed(
            'database_utilities',
            $driver,
            basename($dumpBinary).' / '.basename($restoreBinary),
        );
    }

    /** @return list<DiagnosticCheckData> */
    private function successfulIntegrityChecks(DatabaseIntegrityResult $integrity): array
    {
        $checks = [
            $this->passed(
                'database_integrity',
                'database',
                (string) ($integrity->details[$integrity->driver === 'sqlite' ? 'integrity_check' : 'check_table'] ?? 'OK'),
            ),
            $this->passed(
                'database_identity',
                $integrity->driver,
                $integrity->product,
            ),
            $this->passed(
                'database_version',
                $integrity->driver,
                $integrity->serverVersion,
            ),
        ];

        if ($integrity->driver === 'sqlite') {
            return [
                ...$checks,
                $this->integrityDetailCheck($integrity, 'sqlite_foreign_keys', 'foreign_keys', true),
                $this->integrityDetailCheck($integrity, 'sqlite_journal_mode', 'journal_mode', 'WAL'),
                $this->integrityDetailCheck($integrity, 'sqlite_busy_timeout', 'busy_timeout_ms', 5000),
                $this->integrityDetailCheck($integrity, 'sqlite_synchronous', 'synchronous', 'NORMAL'),
                $this->integrityDetailCheck($integrity, 'sqlite_transaction_mode', 'transaction_mode', 'IMMEDIATE'),
            ];
        }

        return [
            ...$checks,
            $this->integrityDetailCheck($integrity, 'database_charset', 'database_charset', 'utf8mb4'),
            $this->serverCollationCheck($integrity),
            $this->integrityDetailCheck($integrity, 'database_engine', 'table_engine', 'InnoDB'),
        ];
    }

    /** @return list<DiagnosticCheckData> */
    private function unavailableIntegrityChecks(string $driver): array
    {
        $keys = $driver === 'sqlite'
            ? [
                'database_identity',
                'database_version',
                'sqlite_foreign_keys',
                'sqlite_journal_mode',
                'sqlite_busy_timeout',
                'sqlite_synchronous',
                'sqlite_transaction_mode',
            ]
            : [
                'database_identity',
                'database_version',
                'database_charset',
                'database_collation',
                'database_engine',
            ];

        return array_map(
            fn (string $key): DiagnosticCheckData => $this->failed(
                $key,
                $driver,
                'Dettaglio non disponibile perché il controllo di integrità è fallito.',
            ),
            $keys,
        );
    }

    private function integrityDetailCheck(
        DatabaseIntegrityResult $integrity,
        string $key,
        string $detailKey,
        bool|int|string $expected,
    ): DiagnosticCheckData {
        $actual = $integrity->details[$detailKey] ?? null;
        $matches = is_string($expected) && is_string($actual)
            ? strcasecmp($actual, $expected) === 0
            : $actual === $expected;

        return $matches
            ? $this->passed($key, $integrity->driver, $this->stringify($actual))
            : $this->failed($key, $integrity->driver, $this->stringify($actual));
    }

    private function serverCollationCheck(DatabaseIntegrityResult $integrity): DiagnosticCheckData
    {
        $collation = $integrity->details['database_collation'] ?? null;
        $passed = is_string($collation) && str_starts_with(strtolower($collation), 'utf8mb4_');

        return $passed
            ? $this->passed('database_collation', $integrity->driver, $collation)
            : $this->failed('database_collation', $integrity->driver, $this->stringify($collation));
    }

    private function stringify(bool|int|string|null $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value) => (string) $value,
            is_string($value) && $value !== '' => $value,
            default => 'non disponibile',
        };
    }

    private function passed(string $key, string $group, string $detail): DiagnosticCheckData
    {
        return new DiagnosticCheckData($key, $group, DiagnosticCheckStatus::Passed, $detail);
    }

    private function failed(string $key, string $group, string $detail): DiagnosticCheckData
    {
        return new DiagnosticCheckData($key, $group, DiagnosticCheckStatus::Failed, $detail);
    }

    private function warning(string $key, string $group, string $detail): DiagnosticCheckData
    {
        return new DiagnosticCheckData($key, $group, DiagnosticCheckStatus::Warning, $detail);
    }
}
