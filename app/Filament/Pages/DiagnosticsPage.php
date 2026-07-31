<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Filament\Clusters\SettingsCluster;
use App\Services\Diagnostics\ApplicationDiagnostics;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

final class DiagnosticsPage extends Page
{
    protected static ?string $cluster = SettingsCluster::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'diagnostics';

    protected string $view = 'filament.pages.diagnostics-page';

    /** @var array{}|array{ok: bool, runtime: array{php: string, memory_limit: string, missing_extensions: list<string>}, database: array{driver: string, product: string|null, server_version: string|null}, checks: array<string, array{group: string, status: string, detail: string}>} */
    public array $report = [];

    public static function getNavigationLabel(): string
    {
        return __('assestme.diagnostics.navigation');
    }

    public function getTitle(): string
    {
        return __('assestme.diagnostics.title');
    }

    public function mount(ApplicationDiagnostics $diagnostics): void
    {
        $this->report = $diagnostics->run()->toArray();
    }
}
