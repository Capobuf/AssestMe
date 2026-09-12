<?php

declare(strict_types=1);

use Spatie\LaravelPdf\Caching\DefaultPdfCache;
use Spatie\LaravelPdf\Encryption\DefaultPdfEncrypter;
use Spatie\LaravelPdf\Jobs\GeneratePdfJob;

return [
    'driver' => env('LARAVEL_PDF_DRIVER', 'weasyprint'),

    'cache' => [
        'class' => DefaultPdfCache::class,
        'automatic' => env('LARAVEL_PDF_CACHE_AUTOMATIC', false),
        'store' => env('LARAVEL_PDF_CACHE_STORE'),
        'prefix' => 'laravel-pdf',
        'ttl' => env('LARAVEL_PDF_CACHE_TTL', 60 * 60 * 24),
    ],

    'weasyprint' => [
        'binary' => env('LARAVEL_PDF_WEASYPRINT_BINARY', '/usr/bin/weasyprint'),
        'timeout' => null,
    ],

    'job' => GeneratePdfJob::class,
    'encrypter' => DefaultPdfEncrypter::class,
];
