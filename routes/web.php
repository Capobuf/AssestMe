<?php

declare(strict_types=1);

use App\Http\Controllers\DownloadBackupController;
use App\Http\Controllers\DownloadEvidenceController;
use App\Http\Controllers\DownloadGeneratedReportController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Installation\InstallationController;
use App\Http\Controllers\ReportSettingsPreviewController;
use App\Http\Middleware\EnsureApplicationIsNotInstalled;
use Illuminate\Support\Facades\Route;

Route::get('/up', HealthController::class)->name('health');

Route::prefix('install')
    ->name('installation.')
    ->middleware([EnsureApplicationIsNotInstalled::class, 'throttle:installation'])
    ->controller(InstallationController::class)
    ->group(function (): void {
        Route::get('/', 'welcome')->name('welcome');
        Route::post('/welcome', 'continueFromWelcome')->name('welcome.continue');
        Route::get('/requirements', 'runtime')->name('runtime');
        Route::post('/requirements', 'continueFromRuntime')->name('runtime.continue');
        Route::get('/configuration', 'configuration')->name('configuration');
        Route::post('/configuration', 'storeConfiguration')->name('configuration.store');
        Route::get('/database', 'database')->name('database');
        Route::post('/database', 'storeDatabase')->name('database.store');
        Route::get('/administrator', 'administrator')->name('administrator');
        Route::post('/finalize', 'finalize')->name('finalize');
        Route::get('/documentation/hosting', 'documentation')->name('documentation.hosting');
    });

Route::redirect('/', '/admin');

Route::get('/admin/evidence/{evidence}/download', DownloadEvidenceController::class)
    ->middleware('auth')
    ->name('evidence.download');

Route::get('/admin/generated-reports/{generatedReport}/download', DownloadGeneratedReportController::class)
    ->middleware('auth')
    ->name('generated-reports.download');

Route::get('/admin/settings/backups/{archive}/download', DownloadBackupController::class)
    ->where('archive', 'assestme-(?:safety-)?\d{8}-\d{6}(?:-\d+)?\.tar\.gz')
    ->middleware('auth')
    ->name('backups.download');

Route::get('/admin/settings/report/preview/{token}', ReportSettingsPreviewController::class)
    ->middleware('auth')
    ->name('report-settings.preview');
