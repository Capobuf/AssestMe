<?php

declare(strict_types=1);

namespace App\Filament\Pages\Concerns;

use Illuminate\Contracts\View\View;

trait HasParentSettingsNavigation
{
    public function getHeader(): ?View
    {
        return view('filament.pages.partials.integration-settings-header', [
            'page' => $this,
        ]);
    }
}
