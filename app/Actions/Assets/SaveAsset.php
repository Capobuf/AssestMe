<?php

declare(strict_types=1);

namespace App\Actions\Assets;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Client;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as LaravelValidator;

final class SaveAsset
{
    /** @param array<string, mixed> $data */
    public function handle(?Asset $asset, array $data): Asset
    {
        $validator = Validator::make($data, [
            'client_id' => ['required', 'integer', Rule::exists(Client::class, 'id')->withoutTrashed()],
            'site_id' => ['nullable', 'integer', Rule::exists(Site::class, 'id')->withoutTrashed()],
            'asset_type_id' => ['required', 'integer', Rule::exists(AssetType::class, 'id')],
            'name' => ['nullable', 'string', 'max:255'],
            'manufacturer' => ['nullable', 'string', 'max:120'],
            'model' => ['nullable', 'string', 'max:160'],
            'hostname' => ['nullable', 'string', 'max:253'],
            'ip_address' => ['nullable', 'ip', 'max:45'],
            'mac_address' => ['nullable', 'string', 'max:32', 'regex:/^(?:[0-9A-Fa-f]{2}[:-]?){5}[0-9A-Fa-f]{2}$/'],
            'serial_number' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:20000'],
            'notes' => ['nullable', 'string', 'max:20000'],
        ]);

        $validator->after(function (LaravelValidator $validator) use ($asset, $data): void {
            $this->validateIdentifier($validator, $data);
            $this->validateSiteOwnership($validator, $data);
            $this->validateAssetType($validator, $asset, $data);
        });

        /** @var array<string, mixed> $validated */
        $validated = $validator->validate();
        $validated['mac_address'] = $this->normalizeMacAddress($validated['mac_address'] ?? null);

        return DB::transaction(function () use ($asset, $validated): Asset {
            $record = $asset ?? new Asset;
            $record->fill($validated);
            $record->save();

            return $record;
        });
    }

    /** @param array<string, mixed> $data */
    private function validateIdentifier(LaravelValidator $validator, array $data): void
    {
        foreach (['name', 'model', 'hostname', 'ip_address', 'mac_address', 'serial_number'] as $field) {
            if (is_string($data[$field] ?? null) && trim((string) $data[$field]) !== '') {
                return;
            }
        }

        $validator->errors()->add('name', __('assestme.assets.errors.identifier_required'));
    }

    /** @param array<string, mixed> $data */
    private function validateSiteOwnership(LaravelValidator $validator, array $data): void
    {
        if (! is_numeric($data['site_id'] ?? null) || ! is_numeric($data['client_id'] ?? null)) {
            return;
        }

        if (! Site::query()
            ->whereKey((int) $data['site_id'])
            ->where('client_id', (int) $data['client_id'])
            ->exists()) {
            $validator->errors()->add('site_id', __('assestme.assets.errors.site_client'));
        }
    }

    /** @param array<string, mixed> $data */
    private function validateAssetType(LaravelValidator $validator, ?Asset $asset, array $data): void
    {
        if (! is_numeric($data['asset_type_id'] ?? null)) {
            return;
        }

        $assetTypeId = (int) $data['asset_type_id'];
        $keepsCurrentType = $asset !== null && $asset->asset_type_id === $assetTypeId;

        if (! $keepsCurrentType && ! AssetType::query()->whereKey($assetTypeId)->where('is_enabled', true)->exists()) {
            $validator->errors()->add('asset_type_id', __('assestme.assets.errors.asset_type_disabled'));
        }
    }

    private function normalizeMacAddress(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $hex = mb_strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $value));

        return implode(':', str_split($hex, 2));
    }
}
