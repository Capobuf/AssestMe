<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Data\Installation\ApplicationConfigurationData;
use App\Data\Installation\DatabaseConfigurationData;
use App\Enums\SupportedDatabaseDriver;
use App\Support\Installation\BootstrapInstallationKey;
use Dotenv\Dotenv;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;

final readonly class InstallationEnvironmentWriter
{
    public function __construct(private Filesystem $files) {}

    public function writePending(
        ApplicationConfigurationData $application,
        DatabaseConfigurationData $database,
        string $applicationKey,
        ?string $basePath = null,
    ): string {
        if (! BootstrapInstallationKey::isValidKey($applicationKey)) {
            throw new RuntimeException('The final application key is invalid.');
        }

        $values = $this->values($application, $database, $applicationKey);
        $content = $this->serialize($values);
        $environmentPath = $this->environmentPath($basePath);
        $pendingPath = $environmentPath.'.pending';

        $this->assertEnvironmentTargetIsSafe($environmentPath);
        $this->assertEnvironmentTargetIsSafe($pendingPath);
        $this->writeAtomically($pendingPath, $content);
        $this->validateFile($pendingPath, $values);

        return $pendingPath;
    }

    public function activatePending(
        ApplicationConfigurationData $application,
        DatabaseConfigurationData $database,
        string $applicationKey,
        ?string $basePath = null,
    ): void {
        $environmentPath = $this->environmentPath($basePath);
        $pendingPath = $environmentPath.'.pending';
        $values = $this->values($application, $database, $applicationKey);

        $this->assertEnvironmentTargetIsSafe($environmentPath);
        $this->assertEnvironmentTargetIsSafe($pendingPath);

        if (! is_file($pendingPath)) {
            throw new RuntimeException('Pending environment configuration is missing.');
        }

        $this->validateFile($pendingPath, $values);
        $backupPath = $environmentPath.'.backup.'.bin2hex(random_bytes(8));
        $hasExistingEnvironment = is_file($environmentPath);

        try {
            if ($hasExistingEnvironment) {
                if (! @copy($environmentPath, $backupPath) || ! @chmod($backupPath, 0600)) {
                    throw new RuntimeException('Existing environment configuration could not be backed up.');
                }
            }

            if (! @rename($pendingPath, $environmentPath)) {
                throw new RuntimeException('Environment configuration could not be activated atomically.');
            }

            if (! @chmod($environmentPath, 0600)) {
                throw new RuntimeException('Environment configuration permissions could not be secured.');
            }

            $this->validateFile($environmentPath, $values);
        } catch (Throwable $exception) {
            if ($hasExistingEnvironment && is_file($backupPath)) {
                @rename($backupPath, $environmentPath);
            }

            throw $exception;
        } finally {
            if (is_file($backupPath)) {
                $this->files->delete($backupPath);
            }
        }
    }

    /** @return array<string, string> */
    private function values(
        ApplicationConfigurationData $application,
        DatabaseConfigurationData $database,
        string $applicationKey,
    ): array {
        $values = [
            'APP_NAME' => $application->name,
            'APP_ENV' => 'production',
            'APP_KEY' => $applicationKey,
            'APP_DEBUG' => 'false',
            'APP_URL' => $application->url,
            'APP_LOCALE' => $application->locale,
            'APP_FALLBACK_LOCALE' => 'it',
            'APP_TIMEZONE' => $application->timezone,
            'LOG_CHANNEL' => 'stack',
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => $database->driver->value,
            'DB_DATABASE' => $database->database,
            'DB_HOST' => $database->host,
            'DB_PORT' => (string) $database->port,
            'DB_USERNAME' => $database->username,
            'DB_PASSWORD' => $database->password,
            'DB_SOCKET' => $database->socket,
            'DB_CHARSET' => $database->charset,
            'DB_COLLATION' => $database->collation,
            'DB_FOREIGN_KEYS' => 'true',
            'DB_BUSY_TIMEOUT' => '5000',
            'DB_JOURNAL_MODE' => 'WAL',
            'DB_SYNCHRONOUS' => 'NORMAL',
            'DB_TRANSACTION_MODE' => 'IMMEDIATE',
            'CACHE_STORE' => 'file',
            'SESSION_DRIVER' => 'file',
            'SESSION_ENCRYPT' => 'true',
            'SESSION_SECURE_COOKIE' => str_starts_with($application->url, 'https://') ? 'true' : 'false',
            'QUEUE_CONNECTION' => 'sync',
            'FILESYSTEM_DISK' => 'local',
            'LARAVEL_PDF_DRIVER' => 'weasyprint',
            'LARAVEL_PDF_WEASYPRINT_BINARY' => $application->weasyPrintBinary,
            'ASSESTME_PHP_BINARY' => $application->phpBinary,
            'ASSESTME_DB_DUMP_BINARY' => $database->dumpBinary,
            'ASSESTME_DB_RESTORE_BINARY' => $database->restoreBinary,
            'ASSESTME_BACKUP_ROOT' => $application->backupRoot,
            'ASSESTME_VERSION' => (string) config('assestme.version', 'development'),
        ];

        if ($database->driver === SupportedDatabaseDriver::Sqlite) {
            $values['DB_HOST'] = '';
            $values['DB_PORT'] = '';
            $values['DB_USERNAME'] = '';
            $values['DB_PASSWORD'] = '';
            $values['DB_SOCKET'] = '';
            $values['DB_CHARSET'] = '';
            $values['DB_COLLATION'] = '';
        }

        return $values;
    }

    /** @param array<string, string> $values */
    private function serialize(array $values): string
    {
        $lines = [
            '# Generated atomically by the AssestMe installer.',
            '# Edit only after taking and verifying an application backup.',
        ];

        foreach ($values as $key => $value) {
            if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
                throw new RuntimeException('Environment configuration contains an invalid key.');
            }

            if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
                throw new RuntimeException("Environment value for {$key} contains an invalid control character.");
            }

            $escaped = str_replace(
                ['\\', '"', '$', "\r", "\n", "\t"],
                ['\\\\', '\\"', '\\$', '\\r', '\\n', '\\t'],
                $value,
            );
            $lines[] = $key.'="'.$escaped.'"';
        }

        return implode("\n", $lines)."\n";
    }

    /** @param array<string, string> $expected */
    private function validateFile(string $path, array $expected): void
    {
        try {
            $loaded = Dotenv::createArrayBacked(dirname($path), basename($path))->load();
        } catch (Throwable $exception) {
            throw new RuntimeException('Environment configuration did not pass parser validation.', previous: $exception);
        }

        if ($loaded !== $expected) {
            throw new RuntimeException('Environment configuration failed its round-trip validation.');
        }
    }

    private function writeAtomically(string $path, string $contents): void
    {
        $temporaryPath = $path.'.tmp.'.bin2hex(random_bytes(8));

        try {
            if ($this->files->put($temporaryPath, $contents, true) === false
                || ! @chmod($temporaryPath, 0600)
                || $this->files->get($temporaryPath) !== $contents) {
                throw new RuntimeException('Environment configuration could not be written securely.');
            }

            if (! @rename($temporaryPath, $path)) {
                throw new RuntimeException('Environment configuration could not be staged atomically.');
            }
        } finally {
            if (is_file($temporaryPath)) {
                $this->files->delete($temporaryPath);
            }
        }
    }

    private function assertEnvironmentTargetIsSafe(string $path): void
    {
        if (is_link($path) || is_link(dirname($path))) {
            throw new RuntimeException('Environment configuration path must not be a symbolic link.');
        }

        if (! is_writable(dirname($path))) {
            throw new RuntimeException('Environment configuration directory is not writable.');
        }
    }

    private function environmentPath(?string $basePath): string
    {
        if ($basePath === null) {
            $configuredPath = config('assestme.installation.environment_path');

            if (! is_string($configuredPath)
                || ! str_starts_with($configuredPath, DIRECTORY_SEPARATOR)
                || ! is_dir(dirname($configuredPath))) {
                throw new RuntimeException('The configured environment path must have an existing absolute parent directory.');
            }

            return $configuredPath;
        }

        if (! str_starts_with($basePath, DIRECTORY_SEPARATOR) || ! is_dir($basePath)) {
            throw new RuntimeException('Application base path must be an existing absolute directory.');
        }

        return rtrim($basePath, DIRECTORY_SEPARATOR).'/.env';
    }
}
