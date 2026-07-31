<?php

declare(strict_types=1);

namespace App\Services\Database\Snapshot;

use App\Data\Backups\BackupDatabaseData;
use Illuminate\Database\Connection;

interface DatabaseSnapshotter
{
    public function createSnapshot(Connection $connection, string $stage): BackupDatabaseData;
}
