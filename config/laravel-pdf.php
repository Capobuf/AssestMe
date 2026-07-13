<?php

declare(strict_types=1);

use Spatie\LaravelPdf\Caching\DefaultPdfCache;
use Spatie\LaravelPdf\Encryption\DefaultPdfEncrypter;
use Spatie\LaravelPdf\Jobs\GeneratePdfJob;

return [
    'driver' => env('LARAVEL_PDF_DRIVER', 'dompdf'),

    'cache' => [
        'class' => DefaultPdfCache::class,
        'automatic' => env('LARAVEL_PDF_CACHE_AUTOMATIC', false),
        'store' => env('LARAVEL_PDF_CACHE_STORE'),
        'prefix' => 'laravel-pdf',
        'ttl' => env('LARAVEL_PDF_CACHE_TTL', 60 * 60 * 24),
    ],

    'dompdf' => [
        'is_remote_enabled' => env('LARAVEL_PDF_DOMPDF_REMOTE_ENABLED', false),
        'chroot' => env('LARAVEL_PDF_DOMPDF_CHROOT'),
        'page_chrome' => [
            'header' => env('LARAVEL_PDF_HEADER', 'AssestMe — Proof Milestone 0'),
            'footer' => env('LARAVEL_PDF_FOOTER', 'Riservato'),
            'show_cover' => false,
        ],
    ],

    'job' => GeneratePdfJob::class,
    'encrypter' => DefaultPdfEncrypter::class,
];
