<?php

declare(strict_types=1);

use App\Http\Controllers\DownloadAssessmentProofPdfController;
use App\Http\Controllers\DownloadAssessmentProofXlsxController;
use App\Http\Controllers\DownloadEvidenceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/admin/assessments/{assessment}/proof.pdf', DownloadAssessmentProofPdfController::class)
    ->middleware('auth')
    ->name('assessments.proof-pdf');

Route::get('/admin/assessments/{assessment}/proof.xlsx', DownloadAssessmentProofXlsxController::class)
    ->middleware('auth')
    ->name('assessments.proof-xlsx');

Route::get('/admin/evidence/{evidence}/download', DownloadEvidenceController::class)
    ->middleware('auth')
    ->name('evidence.download');
