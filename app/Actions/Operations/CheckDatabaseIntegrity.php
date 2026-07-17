<?php

declare(strict_types=1);

namespace App\Actions\Operations;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CheckDatabaseIntegrity
{
    public function __invoke(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            throw new RuntimeException('Database integrity checks require the configured SQLite connection.');
        }

        $rows = DB::select('PRAGMA integrity_check');
        $results = array_map(
            static fn (object $row): string => strtolower(trim((string) (array_values((array) $row)[0] ?? ''))),
            $rows,
        );

        if ($results !== ['ok']) {
            $detail = implode('; ', array_filter($results));

            throw new RuntimeException('SQLite integrity check failed'.($detail === '' ? '.' : ": {$detail}"));
        }
    }
}
