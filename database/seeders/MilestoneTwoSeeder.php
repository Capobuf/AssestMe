<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Templates\ImportFindingTemplates;
use Illuminate\Database\Seeder;

final class MilestoneTwoSeeder extends Seeder
{
    public function run(): void
    {
        $path = base_path('templates/base-findings.it.json');
        $json = file_get_contents($path);

        if (! is_string($json)) {
            throw new \RuntimeException("Unable to read the base finding library at {$path}.");
        }

        app(ImportFindingTemplates::class)->handle($json, 'replace');
    }
}
