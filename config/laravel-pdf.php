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
            'footer' => env('LARAVEL_PDF_FOOTER', 'AssestMe'),
            'show_cover' => false,
        ],
    ],

    'chrome' => [
        'chrome_binary' => env('LARAVEL_PDF_CHROME_BINARY'),
        'no_sandbox' => env('LARAVEL_PDF_CHROME_NO_SANDBOX', false),
        'timeout' => (int) env('LARAVEL_PDF_CHROME_TIMEOUT', 30_000),
        'startup_timeout' => (int) env('LARAVEL_PDF_CHROME_STARTUP_TIMEOUT', 30),
        'operation_timeout' => (int) env('LARAVEL_PDF_CHROME_OPERATION_TIMEOUT', 30_000),
    ],

    'weasyprint' => [
        'binary' => env('LARAVEL_PDF_WEASYPRINT_BINARY', '/usr/bin/weasyprint'),
        // Debian 12 ships WeasyPrint 57, before the CLI gained --timeout.
        'timeout' => null,
    ],

    'job' => GeneratePdfJob::class,
    'encrypter' => DefaultPdfEncrypter::class,
];
