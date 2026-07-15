<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Models\Client;

final class FindDuplicateClientIdentifiers
{
    /** @return list<string> */
    public function handle(Client $client): array
    {
        $duplicates = [];

        if ($client->vat_number !== null && Client::query()
            ->whereKeyNot($client->getKey())
            ->where('vat_number', $client->vat_number)
            ->exists()) {
            $duplicates[] = 'vat_number';
        }

        if ($client->tax_code !== null && Client::query()
            ->whereKeyNot($client->getKey())
            ->where('tax_code', $client->tax_code)
            ->exists()) {
            $duplicates[] = 'tax_code';
        }

        return $duplicates;
    }
}
