<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

ini_set('memory_limit', '512M');

$database = dirname(__DIR__).'/database/testing.sqlite';

if (! file_exists($database)) {
    touch($database);
}
