<?php

declare(strict_types=1);

use App\Services\GoogleDrive\GoogleDriveSyncService;
use App\Settings\GoogleDriveSettings;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

it('registers the hourly Rome schedule with overlap prevention', function (): void {
    $events = app(Schedule::class)->events();
    $event = collect($events)->first(
        static fn ($event): bool => str_contains($event->command ?? '', 'assestme:google-drive-sync'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 * * * *')
        ->and($event->timezone)->toBe('Europe/Rome')
        ->and($event->withoutOverlapping)->toBeTrue();
});

it('reports exit code two when another run owns the application lock', function (): void {
    $settings = app(GoogleDriveSettings::class);
    $settings->sync_enabled = true;
    $settings->google_account_email = 'admin@example.test';
    $settings->encrypted_refresh_token = 'refresh-secret';
    $settings->root_folder_id = 'root-123';
    $settings->root_folder_name = 'AssestMe';
    $settings->save();
    config()->set('services.google', [
        'client_id' => 'client', 'client_secret' => 'secret',
    ]);
    $lock = Cache::lock(GoogleDriveSyncService::LOCK_NAME, 60);
    expect($lock->get())->toBeTrue();

    try {
        expect(Artisan::call('assestme:google-drive-sync'))->toBe(2)
            ->and(Artisan::output())->toContain('già in esecuzione');
    } finally {
        $lock->release();
    }
});
