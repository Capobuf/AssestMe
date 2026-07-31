<?php

declare(strict_types=1);

namespace App\Services\Database\Snapshot;

final readonly class MySqlSnapshotter extends MySqlCompatibleSnapshotter
{
    protected function expectedDriver(): string
    {
        return 'mysql';
    }

    protected function expectedProduct(): string
    {
        return 'MySQL';
    }

    protected function newDumper(): SafeMySqlDumper
    {
        return (new SafeMySqlDumper)->setGtidPurged('OFF');
    }
}
