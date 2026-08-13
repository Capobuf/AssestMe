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

it('runs post-install optimization in a bounded subprocess', function (): void {
    Log::shouldReceive('info')
        ->once()
        ->with('installer.finalize.artisan_process.begin', ['command' => 'list']);
    Log::shouldReceive('info')
        ->once()
        ->with('installer.finalize.artisan_process.complete', [
            'command' => 'list',
            'exit_code' => 0,
        ]);

    $action = (new ReflectionClass(FinalizeInstallation::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(FinalizeInstallation::class, 'runArtisanSubprocess');
    $method->invoke($action, PHP_BINARY, 'list');
});

it('fails when the post-install Artisan subprocess fails', function (): void {
    $command = 'not-a-real-installation-command';

    Log::shouldReceive('info')
        ->once()
        ->with('installer.finalize.artisan_process.begin', ['command' => $command]);
    Log::shouldReceive('error')
        ->once()
        ->withArgs(
            static fn (string $message, array $context): bool => $message === 'installer.finalize.artisan_process.failure'
                && $context['command'] === $command
                && is_int($context['exit_code'])
                && $context['exit_code'] !== 0,
        );

    $action = (new ReflectionClass(FinalizeInstallation::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(FinalizeInstallation::class, 'runArtisanSubprocess');

    expect(fn () => $method->invoke($action, PHP_BINARY, $command))
        ->toThrow(RuntimeException::class, "The {$command} command did not complete successfully.");
});
