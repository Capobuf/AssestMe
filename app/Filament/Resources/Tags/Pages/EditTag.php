<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tags\Pages;

use App\Actions\Tags\SaveTag;
use App\Filament\Resources\Tags\TagResource;
use App\Models\Tag;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use LogicException;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof Tag) {
            throw new LogicException('The tag resource received an invalid model.');
        }

        return app(SaveTag::class)->handle($record, $data);
    }
}
