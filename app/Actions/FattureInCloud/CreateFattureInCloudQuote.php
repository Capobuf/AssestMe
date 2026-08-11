<?php

declare(strict_types=1);

namespace App\Actions\FattureInCloud;

use App\Data\FattureInCloud\FattureInCloudDocumentData;
use App\Data\FattureInCloud\FattureInCloudQuoteRowData;
use App\Data\FattureInCloud\FattureInCloudVatTypeData;
use App\Enums\AssessmentStatus;
use App\Models\Assessment;
use App\Services\FattureInCloud\FattureInCloudAmbiguousOutcome;
use App\Services\FattureInCloud\FattureInCloudApi;
use App\Settings\FattureInCloudSettings;
use Illuminate\Validation\ValidationException;

final readonly class CreateFattureInCloudQuote
{
    public function __construct(
        private FattureInCloudApi $api,
        private FattureInCloudSettings $settings,
        private FindPreviousFattureInCloudQuote $previous,
    ) {}

    /** @param list<array<string, mixed>> $rows */
    public function __invoke(Assessment $assessment, string $clientId, array $rows): FattureInCloudDocumentData
    {
        $companyId = $this->settings->company_id;
        if (! is_string($companyId) || $companyId === '' || $assessment->status !== AssessmentStatus::Draft || $rows === []) {
            throw ValidationException::withMessages([
                'rows' => __('assestme.fatture_in_cloud.composer.invalid_rows'),
            ]);
        }
        $localClient = $assessment->client;
        $remoteClient = $this->api->client($companyId, $clientId);
        if ($localClient->fatture_in_cloud_company_id !== $companyId
            || $localClient->fatture_in_cloud_client_id !== $clientId
            || $remoteClient === null) {
            throw ValidationException::withMessages([
                'client' => __('assestme.fatture_in_cloud.composer.client_invalid'),
            ]);
        }

        $rowData = [];
        foreach ($rows as $index => $row) {
            $rowData[] = FattureInCloudQuoteRowData::fromState($row, $index + 1);
        }

        $this->validateLiveReferences($assessment, $companyId, $rowData);
        $latest = $this->previous->latest($assessment);
        $parsed = $latest === null ? null : FattureInCloudQuoteLogic::parseMarker($latest->subject);
        $version = ($parsed['version'] ?? 0) + 1;
        $marker = FattureInCloudQuoteLogic::marker($assessment->getKey(), $version);
        $payload = [
            'type' => 'quote',
            'entity' => array_filter([
                'id' => FattureInCloudQuoteRowData::providerIdentifier($clientId),
                'name' => $remoteClient->name,
                'vat_number' => $remoteClient->vatNumber,
                'tax_code' => $remoteClient->taxCode,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''),
            'subject' => $marker,
            'visible_subject' => __('assestme.fatture_in_cloud.composer.visible_subject', [
                'title' => $assessment->title, 'version' => $version,
            ]),
            'items_list' => array_map(
                static fn (FattureInCloudQuoteRowData $row): array => $row->providerItem(),
                $rowData,
            ),
        ];

        try {
            return $this->api->createQuote($companyId, $payload);
        } catch (FattureInCloudAmbiguousOutcome) {
            $matches = array_values(array_filter(
                $this->api->quotes($companyId, $marker),
                static fn (FattureInCloudDocumentData $document): bool => $document->type === 'quote' && $document->subject === $marker,
            ));
            if (count($matches) === 1) {
                return $matches[0];
            }

            throw new FattureInCloudAmbiguousOutcome(__('assestme.fatture_in_cloud.errors.ambiguous_outcome'));
        }
    }

    /** @param list<FattureInCloudQuoteRowData> $rows */
    private function validateLiveReferences(Assessment $assessment, string $companyId, array $rows): void
    {
        $findingIds = collect($rows)->flatMap(
            static fn (FattureInCloudQuoteRowData $row): array => $row->findingIds,
        )->unique()->values();
        if ($assessment->findings()->whereIn('id', $findingIds)->count() !== $findingIds->count()) {
            throw ValidationException::withMessages([
                'rows' => __('assestme.fatture_in_cloud.composer.finding_invalid'),
            ]);
        }

        $enabledVatIds = collect($this->api->vatTypes($companyId))
            ->reject(static fn (FattureInCloudVatTypeData $vat): bool => $vat->disabled)
            ->pluck('id');
        foreach ($rows as $row) {
            if (! $enabledVatIds->containsStrict($row->vatTypeId)) {
                throw ValidationException::withMessages([
                    'rows' => __('assestme.fatture_in_cloud.errors.invalid_vat'),
                ]);
            }
            if ($row->productId === null) {
                continue;
            }
            $product = $this->api->product($companyId, $row->productId);
            if ($product === null || $product->code !== $row->productCode) {
                throw ValidationException::withMessages([
                    'rows' => __('assestme.fatture_in_cloud.composer.product_invalid'),
                ]);
            }
        }
    }
}
