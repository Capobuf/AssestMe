<?php

declare(strict_types=1);

it('keeps application PHP strict and free of debug calls or direct environment access', function (): void {
    $files = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php');

    foreach ($files as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        expect($contents)
            ->toContain('declare(strict_types=1);')
            ->not->toMatch('/\b(?:dd|dump|ray)\s*\(/')
            ->not->toMatch('/\benv\s*\(/');
    }
});

it('keeps business actions independent from Filament', function (): void {
    foreach (File::allFiles(app_path('Actions')) as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        expect($contents)->not->toContain('Filament\\');
    }
});

it('does not contain a PHPStan baseline', function (): void {
    expect(base_path('phpstan-baseline.neon'))->not->toBeFile();
});
