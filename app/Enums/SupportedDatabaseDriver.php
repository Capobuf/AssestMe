<?php

declare(strict_types=1);

namespace App\Enums;

enum SupportedDatabaseDriver: string
{
    case Sqlite = 'sqlite';
    case MySql = 'mysql';
    case MariaDb = 'mariadb';

    public function label(): string
    {
        return match ($this) {
            self::Sqlite => 'SQLite',
            self::MySql => 'MySQL',
            self::MariaDb => 'MariaDB',
        };
    }
}
