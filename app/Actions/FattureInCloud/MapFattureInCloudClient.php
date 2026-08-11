<?php

declare(strict_types=1);

namespace App\Actions\FattureInCloud;

use App\Data\FattureInCloud\FattureInCloudClientData;
use App\Models\Client;
use App\Settings\FattureInCloudSettings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class MapFattureInCloudClient
{
    public function __construct(private FattureInCloudSettings $settings) {}

    public function __invoke(Client $client, FattureInCloudClientData $remote): Client
    {
        $companyId = $this->settings->company_id;
        if (! is_string($companyId) || $companyId === '') {
            throw new RuntimeException(__('assestme.fatture_in_cloud.errors.authorization_required'));
        }

        return DB::transaction(function () use ($client, $companyId, $remote): Client {
            /** @var Client $locked */
            $locked = Client::query()->lockForUpdate()->findOrFail($client->getKey());
            $locked->fatture_in_cloud_company_id = $companyId;
            $locked->fatture_in_cloud_client_id = $remote->id;
            $locked->save();

            return $locked;
        });
    }
}
