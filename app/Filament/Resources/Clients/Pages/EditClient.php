<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Pages;

use App\Actions\Clients\SaveClient;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class EditClient extends EditRecord
{
    use WarnsAboutDuplicateClientIdentifiers;

    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Client) {
            throw new LogicException('The client resource received an invalid model.');
        }

        return app(SaveClient::class)->handle($record, $data);
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Client) {
            $this->warnAboutDuplicateIdentifiers($record);
        }
    }
}
