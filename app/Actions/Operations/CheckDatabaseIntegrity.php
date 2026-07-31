<?php

declare(strict_types=1);

namespace App\Actions\Operations;

use App\Data\Database\DatabaseIntegrityResult;
use App\Services\Database\DatabaseDriverResolver;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;

final readonly class CheckDatabaseIntegrity
{
    public function __construct(private DatabaseDriverResolver $databaseDriverResolver) {}

    public function __invoke(?Connection $connection = null): DatabaseIntegrityResult
    {
        $connection ??= DB::connection();

        return $this->databaseDriverResolver
            ->integrityChecker($connection)
            ->check($connection);
    }
}
