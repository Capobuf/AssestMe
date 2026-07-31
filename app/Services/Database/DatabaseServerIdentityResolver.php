<?php

declare(strict_types=1);

namespace App\Services\Database;

use Illuminate\Database\Connection;
use RuntimeException;

final class DatabaseServerIdentityResolver
{
    /** @return array{product: string, version: string} */
    public function resolve(Connection $connection): array
    {
        $row = $connection->selectOne('SELECT VERSION() AS version, @@version_comment AS version_comment');
        $values = is_object($row) ? array_change_key_case((array) $row, CASE_LOWER) : [];
        $version = trim((string) ($values['version'] ?? ''));
        $comment = trim((string) ($values['version_comment'] ?? ''));
        $identity = strtolower("{$version} {$comment}");

        if ($version === ''
            || $comment === ''
            || preg_match('/[\x00-\x1F\x7F]/', $version) === 1
            || preg_match('/[\x00-\x1F\x7F]/', $comment) === 1) {
            throw new RuntimeException('Database server identity could not be determined.');
        }

        if (str_contains($identity, 'mariadb')) {
            return ['product' => 'MariaDB', 'version' => $version];
        }

        if (str_contains($identity, 'mysql')) {
            return ['product' => 'MySQL', 'version' => $version];
        }

        throw new RuntimeException('Database server product is unsupported.');
    }
}
