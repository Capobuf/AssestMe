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
        $reportedDriver = str_contains($description, 'mariadb')
            ? SupportedDatabaseDriver::MariaDb
            : SupportedDatabaseDriver::MySql;

        return new DatabaseServerIdentityData(
            driver: $reportedDriver,
            product: $reportedDriver->label(),
            version: trim($version),
            versionComment: trim($versionComment),
        );
    }
}
