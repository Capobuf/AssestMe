<?php

declare(strict_types=1);

namespace App\Services\Installation;

use App\Data\Installation\DatabaseConfigurationData;
use App\Enums\SupportedDatabaseDriver;
use App\Services\Database\DatabaseDumpBinaryValidator;
use App\Services\Database\DatabaseRestoreBinaryValidator;
use RuntimeException;

final readonly class DatabaseClientBinaryInspector
{
    public function __construct(
        private DatabaseDumpBinaryValidator $dumpBinaryValidator,
        private DatabaseRestoreBinaryValidator $restoreBinaryValidator,
    ) {}

    public function inspect(DatabaseConfigurationData $configuration): void
    {
        $extension = $configuration->driver === SupportedDatabaseDriver::Sqlite
            ? 'pdo_sqlite'
            : 'pdo_mysql';

        if (! extension_loaded($extension)) {
            throw new RuntimeException("The {$extension} PHP extension is not loaded.");
        }

        if ($configuration->driver === SupportedDatabaseDriver::Sqlite) {
            return;
        }

        $this->dumpBinaryValidator->validate($configuration->driver->value, $configuration->dumpBinary);
        $this->restoreBinaryValidator->validate($configuration->driver->value, $configuration->restoreBinary);
    }
}
