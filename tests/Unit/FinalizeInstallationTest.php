<?php

declare(strict_types=1);

use App\Actions\Installation\FinalizeInstallation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

it('logs only the Artisan command lifecycle and returned exit code', function (): void {
    Artisan::shouldReceive('call')
        ->once()
        ->with('migrate', ['--force' => true])
        ->andReturn(0);
    Log::shouldReceive('info')
        ->once()
        ->with('installer.finalize.artisan.begin', ['command' => 'migrate']);
    Log::shouldReceive('info')
        ->once()
        ->with('installer.finalize.artisan.complete', [
            'command' => 'migrate',
            'exit_code' => 0,
        ]);

    $action = (new ReflectionClass(FinalizeInstallation::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(FinalizeInstallation::class, 'runArtisanCommand');
    $method->invoke($action, 'migrate', ['--force' => true]);
});

it('logs a failed Artisan exit code and still throws', function (): void {
    Artisan::shouldReceive('call')
        ->once()
        ->with('db:seed', ['--force' => true])
        ->andReturn(2);
    Log::shouldReceive('info')
        ->once()
        ->with('installer.finalize.artisan.begin', ['command' => 'db:seed']);
    Log::shouldReceive('error')
        ->once()
        ->with('installer.finalize.artisan.failure', [
            'command' => 'db:seed',
            'exit_code' => 2,
        ]);

    $action = (new ReflectionClass(FinalizeInstallation::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(FinalizeInstallation::class, 'runArtisanCommand');

    expect(fn () => $method->invoke($action, 'db:seed', ['--force' => true]))
        ->toThrow(RuntimeException::class, 'The db:seed command did not complete successfully.');
});
