<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\VerifyBackup;
use App\Data\Installation\ApplicationConfigurationData;
use App\Data\Installation\DatabaseConfigurationData;
use App\Data\Installation\InstallationFinalCheckResult;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\FindingTemplate;
use App\Models\RiskProfile;
use App\Models\User;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Session\FileSessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class InstallationFinalCheck
{
    public function __construct(
        private Filesystem $files,
        private InstallationRuntimeInspector $runtimeInspector,
        private DatabaseCapabilityProbe $databaseCapabilityProbe,
        private CreateBackup $createBackup,
        private VerifyBackup $verifyBackup,
        private SchedulerHeartbeat $schedulerHeartbeat,
        private Migrator $migrator,
    ) {}

    public function run(
        ApplicationConfigurationData $application,
        DatabaseConfigurationData $database,
    ): InstallationFinalCheckResult {
        $checks = [];

        $checks[] = $this->check('configuration', 'Configurazione definitiva', function () use ($application, $database): string {
            if (config('app.url') !== $application->url || config('database.default') !== $database->driver->value) {
                throw new RuntimeException('La configurazione runtime non corrisponde ai valori convalidati.');
            }

            return 'Valori runtime e configurazione pending coerenti.';
        });

        $checks[] = $this->check('database', 'Connessione e capacità database', function () use ($database): string {
            $result = $this->databaseCapabilityProbe->probe($database);

            return "{$result->product} {$result->serverVersion}; probe completo e ripulito.";
        });

        $checks[] = $this->check('migrations', 'Migration pendenti', function (): string {
            $paths = array_values(array_unique([database_path('migrations'), ...$this->migrator->paths()]));
            $files = $this->migrator->getMigrationFiles($paths);
            $pending = array_diff(array_keys($files), $this->migrator->getRepository()->getRan());

            if ($pending !== []) {
                throw new RuntimeException('Sono presenti migration non eseguite.');
            }

            return 'Nessuna migration pendente.';
        });

        $checks[] = $this->check('seed_data', 'Dati iniziali', function (): string {
            if (Category::query()->count() < 18
                || AssetType::query()->count() < 1
                || RiskProfile::query()->count() < 1
                || FindingTemplate::query()->count() < 1) {
                throw new RuntimeException('I dati iniziali obbligatori sono incompleti.');
            }

            return 'Dati iniziali presenti senza record dimostrativi.';
        });

        $checks[] = $this->check('administrator', 'Amministratore unico', function (): string {
            if (User::query()->count() !== 1) {
                throw new RuntimeException('Deve esistere esattamente un amministratore.');
            }

            return 'Esiste esattamente un amministratore.';
        });

        $checks[] = $this->check('storage', 'Storage privato', fn (): string => $this->checkStorage());
        $checks[] = $this->check('cache', 'Cache file', fn (): string => $this->checkCache());
        $checks[] = $this->check('session', 'Sessione tra richieste', fn (): string => $this->checkSession());
        $checks[] = $this->check('transaction', 'Transazione e rollback', fn (): string => $this->checkRollback());

        $checks[] = $this->check('pdf', 'PDF minimo WeasyPrint', function () use ($application): string {
            $inspection = $this->runtimeInspector->inspect(base_path(), $application->phpBinary, $application->weasyPrintBinary);

            if (! $inspection->passed()) {
                throw new RuntimeException('Il controllo runtime o il PDF minimo non è più valido.');
            }

            return 'PDF reale generato con intestazione %PDF-.';
        });

        $backupPath = null;
        $checks[] = $this->check('backup', 'Backup reale', function () use (&$backupPath): string {
            $backupPath = ($this->createBackup)(prune: false);

            return 'Archivio creato: '.basename($backupPath);
        });
        $checks[] = $this->check('backup_verification', 'Verifica backup', function () use (&$backupPath): string {
            if (! is_string($backupPath)) {
                throw new RuntimeException('Il backup da verificare non è disponibile.');
            }

            $this->verifyBackup->handle($backupPath);

            return 'Manifest, percorsi e SHA-256 verificati.';
        });

        $checks[] = $this->check('health', 'Health applicativo', function (): string {
            DB::connection()->select('SELECT 1');

            return 'Bootstrap, database e invarianti applicative rispondono.';
        });

        $checks[] = $this->check('php_cli', 'PHP CLI 8.3', function () use ($application): string {
            $inspection = $this->runtimeInspector->inspect(base_path(), $application->phpBinary, $application->weasyPrintBinary);

            if ($inspection->phpBinary !== $application->phpBinary || ! $inspection->passed()) {
                throw new RuntimeException('Il PHP CLI configurato non supera la verifica.');
            }

            return $application->phpBinary;
        });

        $scheduler = $this->schedulerHeartbeat->status();
        $checks[] = [
            'key' => 'scheduler',
            'label' => 'Laravel Scheduler',
            'status' => $scheduler->isRecent() ? 'passed' : 'pending',
            'detail' => $scheduler->isRecent()
                ? 'Heartbeat recente rilevato.'
                : 'Cron CloudPanel non ancora verificato; configurarlo dopo il completamento.',
        ];

        return new InstallationFinalCheckResult($checks);
    }

    /** @return array{key: string, label: string, status: 'passed'|'failed', detail: string} */
    private function check(string $key, string $label, callable $callback): array
    {
        try {
            $detail = $callback();

            return ['key' => $key, 'label' => $label, 'status' => 'passed', 'detail' => is_string($detail) ? $detail : 'Superato.'];
        } catch (Throwable) {
            return ['key' => $key, 'label' => $label, 'status' => 'failed', 'detail' => 'Il controllo reale non è stato superato.'];
        }
    }

    private function checkStorage(): string
    {
        $directory = storage_path('app/private');
        $this->files->ensureDirectoryExists($directory, 0700, true);
        $path = $directory.'/.installation-final-'.bin2hex(random_bytes(8));
        $payload = bin2hex(random_bytes(32));

        try {
            if ($this->files->put($path, $payload, true) === false || $this->files->get($path) !== $payload) {
                throw new RuntimeException('Storage write/read failed.');
            }
        } finally {
            $this->files->delete($path);
        }

        return 'Scrittura, lettura e cancellazione riuscite.';
    }

    private function checkCache(): string
    {
        $key = 'assestme-install-final-'.Str::random(24);
        $value = Str::random(32);

        try {
            Cache::store('file')->put($key, $value, 60);

            if (Cache::store('file')->get($key) !== $value) {
                throw new RuntimeException('Cache round trip failed.');
            }
        } finally {
            Cache::store('file')->forget($key);
        }

        return 'Scrittura, lettura e rimozione riuscite.';
    }

    private function checkSession(): string
    {
        $path = (string) config('session.files');
        $this->files->ensureDirectoryExists($path, 0700, true);
        $handler = new FileSessionHandler($this->files, $path, 120);
        $id = Str::random(40);
        $first = new Store('assestme-install-check', $handler, $id);
        $first->start();
        $first->put('probe', 'persisted');
        $first->save();

        try {
            $second = new Store('assestme-install-check', $handler, $id);
            $second->start();

            if ($second->get('probe') !== 'persisted') {
                throw new RuntimeException('Session did not persist.');
            }
        } finally {
            $handler->destroy($id);
        }

        return 'Valore persistito tra due istanze di richiesta.';
    }

    private function checkRollback(): string
    {
        $connection = DB::connection();
        $table = 'assestme_final_'.bin2hex(random_bytes(6));
        $schema = $connection->getSchemaBuilder();

        try {
            $schema->create($table, static function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('value');
            });
            $connection->beginTransaction();
            $connection->table($table)->insert(['value' => 'rollback']);
            $connection->rollBack();

            if ($connection->table($table)->count() !== 0) {
                throw new RuntimeException('Database rollback did not restore an empty table.');
            }
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }

            $schema->dropIfExists($table);
        }

        return 'Transazione annullata senza dati residui.';
    }
}
