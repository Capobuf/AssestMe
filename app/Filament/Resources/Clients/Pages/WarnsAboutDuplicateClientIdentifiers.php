<?php

declare(strict_types=1);

namespace App\Filament\Resources\Clients\Pages;

use App\Actions\Clients\FindDuplicateClientIdentifiers;
use App\Models\Client;
use Filament\Notifications\Notification;

trait WarnsAboutDuplicateClientIdentifiers
{
    private function warnAboutDuplicateIdentifiers(Client $client): void
    {
        $duplicates = app(FindDuplicateClientIdentifiers::class)->handle($client);

        if ($duplicates === []) {
            return;
        }

        $labels = array_map(
            static fn (string $field): string => (string) __("assestme.clients.fields.{$field}"),
            $duplicates,
        );

        Notification::make()
            ->warning()
            ->title(__('assestme.clients.duplicate.title'))
            ->body(__('assestme.clients.duplicate.body', ['fields' => implode(', ', $labels)]))
            ->persistent()
            ->send();
    }
}
