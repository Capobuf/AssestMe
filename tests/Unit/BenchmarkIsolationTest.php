<?php

declare(strict_types=1);

use App\Models\Assessment;
use App\Models\Client;
use App\Models\GeneratedReport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

afterEach(function (): void {
    DB::disconnect('sqlite');
});

it('runs the benchmark in a disposable database without changing the active instance', function (): void {
    expect(Artisan::call('migrate:fresh', ['--force' => true]))->toBe(0);

    $client = Client::factory()->create(['legal_name' => 'Istanza da preservare']);
    $database = DB::connection()->getDatabaseName();
    $assessmentCount = Assessment::query()->count();

    $this->artisan('assestme:benchmark', ['--findings' => 1])
        ->expectsOutputToContain('"isolated": true')
        ->assertSuccessful();

    expect(DB::connection()->getDatabaseName())->toBe($database)
        ->and(Client::query()->whereKey($client->getKey())->value('legal_name'))->toBe('Istanza da preservare')
        ->and(Assessment::query()->count())->toBe($assessmentCount)
        ->and(GeneratedReport::query()->count())->toBe(0);
});
