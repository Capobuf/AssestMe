<?php

declare(strict_types=1);

namespace App\Services\Database\Integrity;

use App\Data\Database\DatabaseIntegrityResult;
use Illuminate\Database\Connection;

interface DatabaseIntegrityChecker
{
    public function check(Connection $connection): DatabaseIntegrityResult;
}
