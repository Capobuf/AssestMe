<?php

declare(strict_types=1);

namespace App\Filament\Resources\Sites\Pages;

use App\Actions\Sites\SaveSite;
use App\Filament\Resources\Sites\SiteResource;
use App\Models\Site;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Site) {
            throw new LogicException('The site resource received an invalid model.');
        }

        return app(SaveSite::class)->handle($record, $data);
    }
}
