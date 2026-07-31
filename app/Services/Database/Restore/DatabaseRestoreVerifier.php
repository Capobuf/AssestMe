<?php

declare(strict_types=1);

namespace App\Services\Database\Restore;

use App\Services\Database\DatabaseDriverResolver;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migrator;
use RuntimeException;

final readonly class DatabaseRestoreVerifier
{
    public function __construct(
        private Migrator $migrator,
        private DatabaseDriverResolver $databaseDriverResolver,
    ) {}

    public function verify(Connection $connection): void
    {
        $connection->getPdo();
        $schema = $connection->getSchemaBuilder();

        if (! $schema->hasTable('migrations') || ! $schema->hasTable('users')) {
            throw new RuntimeException('The restored database does not contain the AssestMe core schema.');
        }

        $pending = $this->migrator->usingConnection(
            $connection->getName(),
            function (): array {
                $paths = array_values(array_unique([
                    database_path('migrations'),
                    ...$this->migrator->paths(),
                ]));
                $files = $this->migrator->getMigrationFiles($paths);

                return array_values(array_diff(
                    array_keys($files),
                    $this->migrator->getRepository()->getRan(),
                ));
            },
        );

        if ($pending !== []) {
            throw new RuntimeException('The restored database has pending migrations.');
        }

        if ($connection->table('users')->count() !== 1) {
            throw new RuntimeException('The restored database must contain exactly one administrator.');
        }

        $integrity = $this->databaseDriverResolver
            ->integrityChecker($connection)
            ->check($connection);

        if (! $integrity->healthy) {
            throw new RuntimeException('The restored database failed its integrity check.');
        }
    }
}
