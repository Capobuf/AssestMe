<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Reporting\DomPdfCanvasDriver;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelPdf\Drivers\PdfDriver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DomPdfCanvasDriver::class, fn (): DomPdfCanvasDriver => new DomPdfCanvasDriver(
            config('laravel-pdf.dompdf', []),
        ));

        $this->app->singleton('laravel-pdf.driver.dompdf', fn (): DomPdfCanvasDriver => app(DomPdfCanvasDriver::class));
        $this->app->singleton(PdfDriver::class, fn (): DomPdfCanvasDriver => app(DomPdfCanvasDriver::class));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void {}
}
