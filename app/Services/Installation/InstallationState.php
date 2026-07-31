<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Data\Installation\ApplicationConfigurationData;
use App\Data\Installation\DatabaseConfigurationData;
use App\Data\Installation\InstallationProgressData;
use App\Enums\SupportedDatabaseDriver;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

final readonly class InstallationState
{
    public function __construct(private Filesystem $files) {}

    public function progress(): InstallationProgressData
    {
        $path = $this->statePath();

        if (! $this->files->exists($path)) {
            return new InstallationProgressData((string) Str::uuid(), 'welcome');
        }

        $this->assertPrivateRegularFile($path, 'Installer progress file');

        try {
            $json = Crypt::decryptString($this->files->get($path));
            /** @var array{
             *   schema_version?: int,
             *   installation_id?: string,
             *   step?: string,
             *   application?: array{name: string, url: string, timezone: string, locale: string, backup_root: string, weasyprint_binary: string, php_binary: string}|null,
             *   database?: array{driver: string, database: string, host: string, port: int, username: string, password: string, socket: string, charset: string, collation: string, dump_binary: string, restore_binary: string}|null
             * } $data
             */
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $exception) {
            throw new RuntimeException('Installer progress state is invalid.', previous: $exception);
        }

        if (($data['schema_version'] ?? null) !== 1
            || ! isset($data['installation_id'], $data['step'])
            || ! Str::isUuid($data['installation_id'])) {
            throw new RuntimeException('Installer progress state has an unsupported structure.');
        }

        return new InstallationProgressData(
            installationId: $data['installation_id'],
            step: $data['step'],
            application: isset($data['application']) ? ApplicationConfigurationData::fromArray($data['application']) : null,
            database: isset($data['database']) ? DatabaseConfigurationData::fromArray($data['database']) : null,
        );
    }

    public function hasProgress(): bool
    {
        return $this->files->exists($this->statePath());
    }

    public function save(InstallationProgressData $progress): void
    {
        try {
            $payload = json_encode($progress->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('Installer progress state could not be encoded.', previous: $exception);
        }

        $this->atomicPrivateWrite($this->statePath(), Crypt::encryptString($payload));
    }

    public function removeProgress(): void
    {
        $path = $this->statePath();

        if ($this->files->exists($path) && ! $this->files->delete($path)) {
            throw new RuntimeException('Installer progress state could not be removed.');
        }
    }

    public function isInstalled(): bool
    {
        return $this->installedMetadata() !== null;
    }

    /** @return array{schema_version: int, installed_at: string, application_version: string, database_driver: string}|null */
    public function installedMetadata(): ?array
    {
        $path = $this->lockPath();

        if (! $this->files->exists($path)) {
            return null;
        }

        $this->assertPrivateRegularFile($path, 'Installation lock');

        try {
            /** @var array{schema_version?: int, installed_at?: string, application_version?: string, database_driver?: string} $data */
            $data = json_decode($this->files->get($path), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Installation lock is invalid.', previous: $exception);
        }

        if (($data['schema_version'] ?? null) !== 1
            || ! isset($data['installed_at'], $data['application_version'], $data['database_driver'])
            || SupportedDatabaseDriver::tryFrom($data['database_driver']) === null) {
            throw new RuntimeException('Installation lock has an unsupported structure.');
        }

        return [
            'schema_version' => 1,
            'installed_at' => $data['installed_at'],
            'application_version' => $data['application_version'],
            'database_driver' => $data['database_driver'],
        ];
    }

    public function createInstalledLock(SupportedDatabaseDriver $driver): void
    {
        if ($this->files->exists($this->lockPath())) {
            throw new RuntimeException('Installation lock already exists.');
        }

        try {
            $payload = json_encode([
                'schema_version' => 1,
                'installed_at' => now('UTC')->toIso8601String(),
                'application_version' => (string) config('assestme.version', 'development'),
                'database_driver' => $driver->value,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)."\n";
        } catch (JsonException $exception) {
            throw new RuntimeException('Installation lock could not be encoded.', previous: $exception);
        }

        $this->atomicPrivateWrite($this->lockPath(), $payload, overwrite: false);
    }

    private function atomicPrivateWrite(string $path, string $contents, bool $overwrite = true): void
    {
        $directory = dirname($path);

        if (is_link($directory)) {
            throw new RuntimeException('Installer private directory must not be a symbolic link.');
        }

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0700, true);
        }

        if (! $overwrite && $this->files->exists($path)) {
            throw new RuntimeException('Installer private file already exists.');
        }

        $temporary = $path.'.tmp.'.bin2hex(random_bytes(8));

        try {
            if ($this->files->put($temporary, $contents, true) === false || ! @chmod($temporary, 0600)) {
                throw new RuntimeException('Installer private file could not be written securely.');
            }

            if (! @rename($temporary, $path)) {
                throw new RuntimeException('Installer private file could not be activated atomically.');
            }
        } finally {
            if ($this->files->exists($temporary)) {
                $this->files->delete($temporary);
            }
        }
    }

    private function assertPrivateRegularFile(string $path, string $label): void
    {
        if (is_link($path) || ! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("{$label} must be a readable regular file.");
        }
    }

    private function statePath(): string
    {
        return $this->configuredAbsolutePath('assestme.installation.state_path');
    }

    private function lockPath(): string
    {
        return $this->configuredAbsolutePath('assestme.installation.lock_path');
    }

    private function configuredAbsolutePath(string $key): string
    {
        $path = config($key);

        if (! is_string($path) || ! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("{$key} must be an absolute path.");
        }

        return $path;
    }
}
