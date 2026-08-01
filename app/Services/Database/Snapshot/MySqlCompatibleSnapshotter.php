<?php

declare(strict_types=1);

namespace App\Services\Database\Snapshot;

use App\Data\Backups\BackupDatabaseData;
use App\Services\Database\DatabaseClientBinaryResolver;
use App\Services\Database\DatabaseClientConfigurationResolver;
use App\Services\Database\DatabaseServerIdentityResolver;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;

abstract readonly class MySqlCompatibleSnapshotter implements DatabaseSnapshotter
{
    public function __construct(
        private Filesystem $files,
        private DatabaseClientBinaryResolver $binaryResolver,
        private DatabaseClientConfigurationResolver $configurationResolver,
        private DatabaseServerIdentityResolver $identityResolver,
    ) {}

    final public function createSnapshot(Connection $connection, string $stage): BackupDatabaseData
    {
        $driver = $connection->getDriverName();

        if ($driver !== $this->expectedDriver()) {
            throw new RuntimeException("The {$this->expectedProduct()} snapshotter received a {$driver} connection.");
        }

        $identity = $this->identityResolver->resolve($connection);
        $product = $identity['product'];
        $serverVersion = $identity['version'];

        if ($product !== $this->expectedProduct()) {
            throw new RuntimeException(
                "Database product mismatch: configured {$this->expectedProduct()}, detected {$product} {$serverVersion}.",
            );
        }

        $binary = $this->binaryResolver->resolveDump($driver);

        if ($binary === null) {
            throw new RuntimeException($this->missingDumpClientMessage());
        }
        $configuration = $this->configurationResolver->resolve($connection);
        $destination = $stage.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'database.sql';
        $this->files->ensureDirectoryExists(dirname($destination), 0700, true);

        if (file_exists($destination) || is_link($destination)) {
            throw new RuntimeException('The SQL snapshot destination already exists.');
        }

        $dumper = $this->newDumper();
        $dumper
            ->setValidatedDumpBinary($binary)
            ->setHost($configuration->host)
            ->setPort($configuration->port)
            ->setDbName($configuration->database)
            ->setUserName($configuration->username)
            ->setPassword($configuration->password)
            ->setSocket($configuration->socket)
            ->useSingleTransaction()
            ->useQuick()
            ->skipLockTables()
            ->setDefaultCharacterSet('utf8mb4')
            ->setTimeout(120);

        try {
            $dumper->dumpToFile($destination);
        } catch (Throwable) {
            throw new RuntimeException("The {$this->expectedProduct()} database snapshot could not be created.");
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            throw new RuntimeException("The {$this->expectedProduct()} database snapshot is empty.");
        }

        return new BackupDatabaseData(
            driver: $driver,
            product: $product,
            serverVersion: $serverVersion,
            format: 'sql',
            path: 'database/database.sql',
        );
    }

    abstract protected function expectedDriver(): string;

    abstract protected function expectedProduct(): string;

    abstract protected function newDumper(): SafeMySqlDumper|SafeMariaDbDumper;

    private function missingDumpClientMessage(): string
    {
        return $this->expectedDriver() === 'mariadb'
            ? 'Backup database non ancora disponibile. Installare il pacchetto mariadb-client; AssestMe rileverà automaticamente mariadb-dump.'
            : 'Backup database non ancora disponibile. Installare un client MySQL che fornisca mysqldump; AssestMe lo rileverà automaticamente.';
    }
}
