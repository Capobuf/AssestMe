<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class IdempotencyKeyMismatch extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The request UUID has already been used with a different workspace payload.');
    }
}
