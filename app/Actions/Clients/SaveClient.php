<?php

declare(strict_types=1);

namespace App\Actions\Clients;

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class SaveClient
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Client $client, array $data): Client
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'tax_code' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:254'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'logo_path' => ['nullable', 'string', 'max:1024'],
            'internal_notes' => ['nullable', 'string', 'max:20000'],
        ])->validate();

        $validated['vat_number'] = $this->normalizeIdentifier($validated['vat_number'] ?? null);
        $validated['tax_code'] = $this->normalizeIdentifier($validated['tax_code'] ?? null);
        $validated['country'] = mb_strtoupper((string) $validated['country']);

        return DB::transaction(function () use ($client, $validated): Client {
            $record = $client ?? new Client;
            $record->fill($validated);
            $record->save();

            return $record;
        });
    }

    private function normalizeIdentifier(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_strtoupper((string) preg_replace('/\s+/u', '', $value));
    }
}
