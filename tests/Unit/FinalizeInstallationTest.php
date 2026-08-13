<?php

declare(strict_types=1);

use App\Actions\Installation\FinalizeInstallation;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

uses(TestCase::class);

it('uses bounded subprocesses for every installer Artisan command', function (): void {
    $source = (string) file_get_contents(base_path('app/Actions/Installation/FinalizeInstallation.php'));

    expect($source)
        ->not->toContain('Artisan::call')
        ->toContain("'migrate',\n            ['--force', '--isolated'],\n            \$pendingEnvironment")
        ->toContain("'db:seed',\n            ['--force'],\n            \$pendingEnvironment")
        ->toContain("'optimize:clear',\n            environment: \$pendingEnvironment")
        ->toContain("'optimize',\n                environment: \$pendingEnvironment")
        ->toContain('$process->setTimeout(20);');
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
    $method->invoke($action, PHP_BINARY, 'list', [], ['APP_ENV' => 'testing']);
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
