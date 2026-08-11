<?php

declare(strict_types=1);

namespace App\Actions\FattureInCloud;

use App\Data\FattureInCloud\FattureInCloudClientData;
use App\Data\FattureInCloud\FattureInCloudClientResolution;
use App\Models\Client;
use App\Services\FattureInCloud\FattureInCloudApi;
use App\Settings\FattureInCloudSettings;

final readonly class ResolveFattureInCloudClient
{
    public function __construct(
        private FattureInCloudApi $api,
        private FattureInCloudSettings $settings,
        private MapFattureInCloudClient $map,
    ) {}

    public function __invoke(Client $client): FattureInCloudClientResolution
    {
        $companyId = $this->settings->company_id;
        if (! is_string($companyId) || $companyId === '') {
            return new FattureInCloudClientResolution(null);
        }

        if ($client->fatture_in_cloud_company_id === $companyId
            && is_string($client->fatture_in_cloud_client_id)) {
            $mapped = $this->api->client($companyId, $client->fatture_in_cloud_client_id);
            if ($mapped instanceof FattureInCloudClientData) {
                return new FattureInCloudClientResolution($mapped);
            }
        }

        if ($client->fatture_in_cloud_company_id !== null || $client->fatture_in_cloud_client_id !== null) {
            $client->forceFill([
                'fatture_in_cloud_company_id' => null,
                'fatture_in_cloud_client_id' => null,
            ])->save();
        }

        $candidates = [];
        $lookups = [];
        $vatNumber = FattureInCloudFiscalIdentifier::vat($client->vat_number);
        if ($vatNumber !== null) {
            $lookups['vat_number:'.$vatNumber] = ['vat_number', $vatNumber];
            if (preg_match('/^[0-9]{11}$/D', $vatNumber) === 1) {
                $lookups['vat_number:IT'.$vatNumber] = ['vat_number', 'IT'.$vatNumber];
            }
        }
        $taxCode = FattureInCloudFiscalIdentifier::taxCode($client->tax_code);
        if ($taxCode !== null) {
            $lookups['tax_code:'.$taxCode] = ['tax_code', $taxCode];
        }
        foreach ($lookups as [$field, $identifier]) {
            foreach ($this->api->clients($companyId, $field, $identifier) as $candidate) {
                if ($this->exactFiscalMatch($client, $candidate)) {
                    $candidates[$candidate->id] = $candidate;
                }
            }
        }

        $matches = array_values($candidates);
        if (count($matches) === 1) {
            ($this->map)($client, $matches[0]);

            return new FattureInCloudClientResolution($matches[0]);
        }

        return new FattureInCloudClientResolution(null, $matches);
    }

    public function create(Client $client): FattureInCloudClientData
    {
        $companyId = $this->settings->company_id;
        if (! is_string($companyId) || $companyId === '') {
            throw new \RuntimeException(__('assestme.fatture_in_cloud.errors.authorization_required'));
        }

        $remote = $this->api->createClient($companyId, $client);
        ($this->map)($client, $remote);

        return $remote;
    }

    private function exactFiscalMatch(Client $local, FattureInCloudClientData $remote): bool
    {
        $localVat = FattureInCloudFiscalIdentifier::vat($local->vat_number);
        $remoteVat = FattureInCloudFiscalIdentifier::vat($remote->vatNumber);
        $localTax = FattureInCloudFiscalIdentifier::taxCode($local->tax_code);
        $remoteTax = FattureInCloudFiscalIdentifier::taxCode($remote->taxCode);

        return ($localVat !== null && $remoteVat !== null && hash_equals($localVat, $remoteVat))
            || ($localTax !== null && $remoteTax !== null && hash_equals($localTax, $remoteTax));
    }
}
