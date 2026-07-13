<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Pages;

use App\Actions\Clients\SaveClient;
use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateClient extends CreateRecord
{
    use WarnsAboutDuplicateClientIdentifiers;

    protected static string $resource = ClientResource::class;

    /** @param array<string, mixed> $data */
    protected function handleRecordCreation(array $data): Model
    {
        return app(SaveClient::class)->handle(null, $data);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if ($record instanceof Client) {
            $this->warnAboutDuplicateIdentifiers($record);
        }
    }
}
