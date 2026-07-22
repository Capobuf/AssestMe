<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Assessment;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Client;
use App\Models\EffortLevel;
use App\Models\Evidence;
use App\Models\FindingTemplate;
use App\Models\GeneratedReport;
use App\Models\RiskProfile;
use App\Models\Site;
use App\Policies\SingletonAdministratorPolicy;
use App\Services\Reporting\DomPdfCanvasDriver;
use App\Support\CanaryClusterRequester;
use Baspa\FilamentCanary\Sweep\Requester;
use Illuminate\Support\Facades\Gate;
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
    public function boot(): void
    {
        if ($this->app->environment('testing') && interface_exists(Requester::class)) {
            $this->app->bind(Requester::class, CanaryClusterRequester::class);
        }

        Gate::policy(Assessment::class, SingletonAdministratorPolicy::class);
        Gate::policy(Asset::class, SingletonAdministratorPolicy::class);
        Gate::policy(AssetType::class, SingletonAdministratorPolicy::class);
        Gate::policy(Category::class, SingletonAdministratorPolicy::class);
        Gate::policy(Client::class, SingletonAdministratorPolicy::class);
        Gate::policy(EffortLevel::class, SingletonAdministratorPolicy::class);
        Gate::policy(Evidence::class, SingletonAdministratorPolicy::class);
        Gate::policy(FindingTemplate::class, SingletonAdministratorPolicy::class);
        Gate::policy(GeneratedReport::class, SingletonAdministratorPolicy::class);
        Gate::policy(RiskProfile::class, SingletonAdministratorPolicy::class);
        Gate::policy(Site::class, SingletonAdministratorPolicy::class);
    }
}
