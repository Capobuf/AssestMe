<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Data\Installation\DatabaseConfigurationData;
use App\Data\Installation\InstallationDatabaseClassificationData;
use App\Data\Installation\InstallationDatabaseStatus;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class InstallationDatabaseClassifier
{
    public const MARKER_TABLE = 'assestme_installation_marker';

    private const CONNECTION = 'install_probe';

    /** @var list<string> */
    private const COMPLETE_TABLES = ['migrations', 'settings', 'users'];

    public function classify(
        DatabaseConfigurationData $configuration,
        string $installationId,
    ): InstallationDatabaseClassificationData {
        $this->assertInstallationId($installationId);

        try {
            $connection = $this->connect($configuration);

            return $this->classifyConnection($connection, $installationId);
        } catch (InstallationDatabaseClassificationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new InstallationDatabaseClassificationException(
                'The database installation state could not be classified.',
            );
        } finally {
            $this->disconnect();
        }
    }

    public function markPartial(
        DatabaseConfigurationData $configuration,
        string $installationId,
    ): void {
        $this->assertInstallationId($installationId);

        try {
            $connection = $this->connect($configuration);
            $classification = $this->classifyConnection($connection, $installationId);

            if ($classification->status === InstallationDatabaseStatus::Foreign) {
                throw new InstallationDatabaseClassificationException(
                    'A foreign or differently marked database must not be modified by the installer.',
                );
            }

            if ($classification->status !== InstallationDatabaseStatus::Empty) {
                return;
            }

            $connection->getSchemaBuilder()->create(self::MARKER_TABLE, static function (Blueprint $table): void {
                $table->string('installation_id', 36)->primary();
                $table->string('state', 16);
                $table->timestamps();
            });
            $connection->table(self::MARKER_TABLE)->insert([
                'installation_id' => $installationId,
                'state' => 'partial',
                'created_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
        } catch (InstallationDatabaseClassificationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new InstallationDatabaseClassificationException(
                'The installation marker could not be created safely.',
            );
        } finally {
            $this->disconnect();
        }
    }

    public function markComplete(
        DatabaseConfigurationData $configuration,
        string $installationId,
    ): void {
        $this->assertInstallationId($installationId);

        try {
            $connection = $this->connect($configuration);
            $classification = $this->classifyConnection($connection, $installationId);

            if ($classification->status === InstallationDatabaseStatus::Complete) {
                return;
            }

            if ($classification->status !== InstallationDatabaseStatus::RecognizedPartial) {
                throw new InstallationDatabaseClassificationException(
                    'Only the matching partial AssestMe installation can be marked complete.',
                );
            }

            if (! $this->hasCompleteApplicationSchema($connection, $classification->tables)) {
                throw new InstallationDatabaseClassificationException(
                    'The matching AssestMe installation is not complete and cannot be finalized.',
                );
            }

            if ($connection->table(self::MARKER_TABLE)
                ->where('installation_id', $installationId)
                ->update(['state' => 'complete', 'updated_at' => now('UTC')]) !== 1) {
                throw new InstallationDatabaseClassificationException(
                    'The matching installation marker could not be finalized.',
                );
            }
        } catch (InstallationDatabaseClassificationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new InstallationDatabaseClassificationException(
                'The installation marker could not be finalized safely.',
            );
        } finally {
            $this->disconnect();
        }
    }

    private function classifyConnection(
        Connection $connection,
        string $installationId,
    ): InstallationDatabaseClassificationData {
        $connection->getPdo();
        $tables = $this->applicationTables($connection);

        if ($tables === []) {
            return new InstallationDatabaseClassificationData(InstallationDatabaseStatus::Empty, []);
        }

        if (! in_array(self::MARKER_TABLE, $tables, true)) {
            return new InstallationDatabaseClassificationData(InstallationDatabaseStatus::Foreign, $tables);
        }

        $marker = $this->readMarker($connection);
        if ($marker === null) {
            return new InstallationDatabaseClassificationData(InstallationDatabaseStatus::Foreign, $tables);
        }

        if (! hash_equals($marker['installation_id'], $installationId)) {
            return new InstallationDatabaseClassificationData(
                InstallationDatabaseStatus::Foreign,
                $tables,
                $marker['installation_id'],
            );
        }

        if ($marker['state'] === 'complete'
            && $this->hasCompleteApplicationSchema($connection, $tables)) {
            return new InstallationDatabaseClassificationData(
                InstallationDatabaseStatus::Complete,
                $tables,
                $marker['installation_id'],
            );
        }

        if (in_array($marker['state'], ['partial', 'complete'], true)) {
            return new InstallationDatabaseClassificationData(
                InstallationDatabaseStatus::RecognizedPartial,
                $tables,
                $marker['installation_id'],
            );
        }

        return new InstallationDatabaseClassificationData(
            InstallationDatabaseStatus::Foreign,
            $tables,
            $marker['installation_id'],
        );
    }

    /**
     * @return array{installation_id: string, state: string}|null
     */
    private function readMarker(Connection $connection): ?array
    {
        try {
            $rows = $connection->table(self::MARKER_TABLE)
                ->limit(2)
                ->get(['installation_id', 'state']);
        } catch (Throwable) {
            return null;
        }

        if ($rows->count() !== 1) {
            return null;
        }

        $row = $rows->first();
        if (! is_object($row)) {
            return null;
        }

        $values = get_object_vars($row);
        $installationId = $values['installation_id'] ?? null;
        $state = $values['state'] ?? null;

        if (! is_string($installationId)
            || ! Str::isUuid($installationId)
            || ! is_string($state)) {
            return null;
        }

        return ['installation_id' => $installationId, 'state' => $state];
    }

    /** @param list<string> $tables */
    private function hasCompleteApplicationSchema(Connection $connection, array $tables): bool
    {
        foreach (self::COMPLETE_TABLES as $requiredTable) {
            if (! in_array($requiredTable, $tables, true)) {
                return false;
            }
        }

        try {
            return $connection->table('migrations')->count() > 0
                && $connection->table('settings')->count() > 0
                && $connection->table('users')->count() === 1;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    private function applicationTables(Connection $connection): array
    {
        $tables = array_values(array_filter(
            $connection->getSchemaBuilder()->getTableListing(schemaQualified: false),
            static fn (string $table): bool => ! str_starts_with($table, 'sqlite_'),
        ));
        sort($tables, SORT_STRING);

        return $tables;
    }

    private function assertInstallationId(string $installationId): void
    {
        if (! Str::isUuid($installationId)) {
            throw new InstallationDatabaseClassificationException(
                'The installation marker identifier must be a UUID.',
            );
        }
    }

    private function connect(DatabaseConfigurationData $configuration): Connection
    {
        Config::set('database.connections.'.self::CONNECTION, $configuration->toLaravelConfig());
        DB::purge(self::CONNECTION);

        return DB::connection(self::CONNECTION);
    }

    private function disconnect(): void
    {
        DB::purge(self::CONNECTION);
        Config::set('database.connections.'.self::CONNECTION, null);
    }
}
