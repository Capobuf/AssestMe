<?php

declare(strict_types=1);

namespace App\Services\Database\Restore;

use App\Services\Database\DatabaseClientBinaryResolver;
use App\Services\Database\DatabaseClientConfigurationResolver;
use App\Services\Database\DatabaseClientOptionFile;
use App\Services\Database\DatabaseClientProcessEnvironment;
use App\Services\Database\DatabaseServerIdentityResolver;
use Illuminate\Database\Connection;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

abstract class MySqlCompatibleRestorer implements DatabaseRestorer
{
    public function __construct(
        private readonly DatabaseClientBinaryResolver $binaryResolver,
        private readonly DatabaseClientConfigurationResolver $configurationResolver,
        private readonly DatabaseServerIdentityResolver $identityResolver,
        private readonly DatabaseClientOptionFile $optionFile,
        private readonly DatabaseClientProcessEnvironment $processEnvironment,
    ) {}

    final public function restore(Connection $connection, string $source): void
    {
        $driver = $connection->getDriverName();

        if ($driver !== $this->expectedDriver()) {
            throw new RuntimeException("The {$this->expectedProduct()} restorer received a {$driver} connection.");
        }

        $identity = $this->identityResolver->resolve($connection);

        if ($identity['product'] !== $this->expectedProduct()) {
            throw new RuntimeException(
                "Database product mismatch: configured {$this->expectedProduct()}, detected {$identity['product']} {$identity['version']}.",
            );
        }

        $this->validateSource($source);
        $binary = $this->binaryResolver->resolveRestore($driver);

        if ($binary === null) {
            throw new RuntimeException($this->missingRestoreClientMessage());
        }
        $configuration = $this->configurationResolver->resolve($connection);
        $credentialsPath = $this->optionFile->create(
            dirname($source),
            $configuration->host,
            $configuration->port,
            $configuration->username,
            $configuration->password,
            $configuration->socket,
        );
        $input = @fopen($source, 'rb');
        $failed = false;

        if ($input === false) {
            $this->optionFile->delete($credentialsPath);

            throw new RuntimeException("The {$this->expectedProduct()} restore payload could not be opened.");
        }

        try {
            $process = new Process(
                [
                    $binary,
                    "--defaults-file={$credentialsPath}",
                    '--binary-mode',
                    '--default-character-set=utf8mb4',
                    '--local-infile=0',
                    "--database={$configuration->database}",
                ],
                env: $this->processEnvironment->forDriver($driver, $credentialsPath),
                input: $input,
                timeout: 600,
            );
            $process->disableOutput();
            $process->run();
            $failed = ! $process->isSuccessful();
        } catch (Throwable) {
            $failed = true;
        } finally {
            if (! fclose($input)) {
                $failed = true;
            }

            if (! $this->optionFile->delete($credentialsPath)) {
                $failed = true;
            }
        }

        if ($failed) {
            throw new RuntimeException("The {$this->expectedProduct()} database restore process failed.");
        }
    }

    abstract protected function expectedDriver(): string;

    abstract protected function expectedProduct(): string;

    private function missingRestoreClientMessage(): string
    {
        return $this->expectedDriver() === 'mariadb'
            ? 'Restore database non disponibile. Installare il pacchetto mariadb-client; AssestMe rileverà automaticamente mariadb.'
            : 'Restore database non disponibile. Installare un client MySQL che fornisca mysql; AssestMe lo rileverà automaticamente.';
    }

    private function validateSource(string $source): void
    {
        if (! str_starts_with($source, DIRECTORY_SEPARATOR)
            || is_link($source)
            || ! is_file($source)
            || ! is_readable($source)
            || filesize($source) === 0) {
            throw new RuntimeException('The verified SQL restore payload is invalid.');
        }

        $prefix = @file_get_contents($source, false, null, 0, 1024 * 1024);

        if (! is_string($prefix)
            || str_contains($prefix, "\0")
            || preg_match('/\bCREATE\s+TABLE\b/i', $prefix) !== 1) {
            throw new RuntimeException('The verified SQL restore payload does not contain an AssestMe schema.');
        }
    }
}
