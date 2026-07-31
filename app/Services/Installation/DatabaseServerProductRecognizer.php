<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Data\Installation\DatabaseServerIdentityData;
use App\Enums\SupportedDatabaseDriver;

final class DatabaseServerProductRecognizer
{
    public function recognize(
        SupportedDatabaseDriver $selectedDriver,
        string $version,
        string $versionComment,
    ): DatabaseServerIdentityData {
        if ($selectedDriver === SupportedDatabaseDriver::Sqlite) {
            throw new DatabaseCapabilityProbeException('Server product recognition is not applicable to SQLite.');
        }

        $description = mb_strtolower(trim($version.' '.$versionComment));
        $reportedDriver = match (true) {
            str_contains($description, 'mariadb') => SupportedDatabaseDriver::MariaDb,
            str_contains($description, 'mysql') => SupportedDatabaseDriver::MySql,
            default => null,
        };

        if (! $reportedDriver instanceof SupportedDatabaseDriver) {
            throw new DatabaseCapabilityProbeException(
                'The database server could not be recognized as MySQL or MariaDB.',
            );
        }

        if ($selectedDriver !== $reportedDriver) {
            throw new DatabaseCapabilityProbeException(
                "Selected {$selectedDriver->label()}, but the server reports {$reportedDriver->label()}.",
            );
        }

        return new DatabaseServerIdentityData(
            driver: $reportedDriver,
            product: $reportedDriver->label(),
            version: trim($version),
            versionComment: trim($versionComment),
        );
    }
}
