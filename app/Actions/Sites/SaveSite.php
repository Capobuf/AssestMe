<?php

declare(strict_types=1);

namespace App\Actions\Sites;

use App\Models\Client;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class SaveSite
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Site $site, array $data): Site
    {
        /** @var array<string, mixed> $validated */
        $validated = Validator::make($data, [
            'client_id' => ['required', 'integer', Rule::exists(Client::class, 'id')->withoutTrashed()],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'description' => ['nullable', 'string', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:20000'],
        ])->validate();

        $validated['country'] = mb_strtoupper((string) $validated['country']);

        return DB::transaction(function () use ($site, $validated): Site {
            $record = $site ?? new Site;
            $record->fill($validated);
            $record->save();

            return $record;
        });
    }
}
