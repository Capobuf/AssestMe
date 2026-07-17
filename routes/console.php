<?php

declare(strict_types=1);

use App\Actions\Assessments\PurgeExpiredWorkspaceSaveRequests;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('assestme:backup')
    ->dailyAt('02:30')
    ->timezone('Europe/Rome')
    ->withoutOverlapping();

Schedule::command('assestme:integrity-check')
    ->dailyAt('03:30')
    ->timezone('Europe/Rome')
    ->withoutOverlapping();

Schedule::call(fn (): int => app(PurgeExpiredWorkspaceSaveRequests::class)())
    ->name('assestme:purge-workspace-save-requests')
    ->hourly()
    ->withoutOverlapping();
