<?php

declare(strict_types=1);

use App\Http\Controllers\DownloadEvidenceController;
use App\Http\Controllers\DownloadGeneratedReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/admin/evidence/{evidence}/download', DownloadEvidenceController::class)
    ->middleware('auth')
    ->name('evidence.download');

Route::get('/admin/generated-reports/{generatedReport}/download', DownloadGeneratedReportController::class)
    ->middleware('auth')
    ->name('generated-reports.download');
