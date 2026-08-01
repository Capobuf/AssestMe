<?php

declare(strict_types=1);

use App\Data\Installation\DatabaseConfigurationData;
use App\Enums\SupportedDatabaseDriver;
use App\Services\Installation\DatabaseCapabilityProbe;
use Illuminate\Support\Facades\DB;

it('executes the complete capability probe against the selected real test server', function (): void {
    $driver = SupportedDatabaseDriver::from((string) config('database.default'));

    if ($driver === SupportedDatabaseDriver::Sqlite) {
        expect(extension_loaded('pdo_sqlite'))->toBeTrue();

        return;
    }

    $connection = DB::connection();
    $configuration = config('database.connections.'.$driver->value);

    if (! is_array($configuration)) {
        throw new RuntimeException('The selected server connection is not configured.');
    }

    $probe = app(DatabaseCapabilityProbe::class)->probe(new DatabaseConfigurationData(
        driver: SupportedDatabaseDriver::MySql,
        database: (string) ($configuration['database'] ?? ''),
        host: (string) ($configuration['host'] ?? ''),
        port: (int) ($configuration['port'] ?? 3306),
        username: (string) ($configuration['username'] ?? ''),
        password: (string) ($configuration['password'] ?? ''),
        socket: (string) ($configuration['unix_socket'] ?? ''),
        charset: (string) ($configuration['charset'] ?? 'utf8mb4'),
        collation: (string) ($configuration['collation'] ?? 'utf8mb4_unicode_ci'),
    ));

    $remainingProbeTables = array_values(array_filter(
        $connection->getSchemaBuilder()->getTableListing(schemaQualified: false),
        static fn (string $table): bool => str_starts_with($table, 'assestme_probe_'),
    ));

    expect($probe->driver)->toBe($driver)
        ->and($probe->product)->toBe($driver->label())
        ->and($probe->serverVersion)->not->toBeEmpty()
        ->and($probe->checks)->toContain('utf8mb4', 'innodb', 'cleanup')
        ->and($remainingProbeTables)->toBe([]);
});
