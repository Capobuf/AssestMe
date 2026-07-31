<?php

declare(strict_types=1);

namespace App\Services\Database\Snapshot;

final readonly class MariaDbSnapshotter extends MySqlCompatibleSnapshotter
{
    protected function expectedDriver(): string
    {
        return 'mariadb';
    }

    protected function expectedProduct(): string
    {
        return 'MariaDB';
    }

    protected function newDumper(): SafeMariaDbDumper
    {
        return new SafeMariaDbDumper;
    }
}
