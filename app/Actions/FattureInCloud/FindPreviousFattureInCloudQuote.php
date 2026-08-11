<?php

declare(strict_types=1);

namespace App\Actions\FattureInCloud;

use App\Data\FattureInCloud\FattureInCloudDocumentData;
use App\Data\FattureInCloud\FattureInCloudDocumentItemData;
use App\Models\Assessment;
use App\Services\FattureInCloud\FattureInCloudAmbiguousOutcome;
use App\Services\FattureInCloud\FattureInCloudApi;
use App\Settings\FattureInCloudSettings;

final readonly class FindPreviousFattureInCloudQuote
{
    public function __construct(
        private FattureInCloudApi $api,
        private FattureInCloudSettings $settings,
    ) {}

    public function latest(Assessment $assessment): ?FattureInCloudDocumentData
    {
        $companyId = $this->settings->company_id;
        if (! is_string($companyId)) {
            return null;
        }
        $latest = null;
        $latestVersion = 0;
        $latestCount = 0;
        foreach ($this->api->quotes($companyId, '[ASSESTME assessment='.$assessment->getKey()) as $document) {
            $marker = FattureInCloudQuoteLogic::parseMarker($document->subject);
            if ($document->type !== 'quote' || $marker === null
                || $marker['assessment_id'] !== $assessment->getKey()
                || $marker['version'] < $latestVersion) {
                continue;
            }
            if ($marker['version'] === $latestVersion) {
                $latestCount++;

                continue;
            }
            $latest = $document;
            $latestVersion = $marker['version'];
            $latestCount = 1;
        }
        if ($latestCount > 1) {
            throw new FattureInCloudAmbiguousOutcome(
                __('assestme.fatture_in_cloud.composer.previous_ambiguous'),
            );
        }

        return $latest;
    }

    /** @return list<array<string, mixed>> */
    public function load(Assessment $assessment, string $documentId): array
    {
        $companyId = $this->settings->company_id;
        if (! is_string($companyId)) {
            return [];
        }
        $document = $this->api->quote($companyId, $documentId);
        $marker = $document === null ? null : FattureInCloudQuoteLogic::parseMarker($document->subject);
        if ($document === null || $marker === null || $marker['assessment_id'] !== $assessment->getKey()) {
            return [];
        }
        $validIds = $assessment->findings()->pluck('id')->mapWithKeys(
            static fn (int $id): array => [FattureInCloudQuoteLogic::findingReference($id) => $id],
        );

        return array_map(static function (FattureInCloudDocumentItemData $item) use ($validIds): array {
            $description = $item->description;
            preg_match_all('/(?<![A-Z0-9-])F-[0-9]{6}(?![0-9])/', $description, $matches);
            $findingIds = collect($matches[0])->unique()->map(
                static fn (string $reference): ?int => $validIds->get($reference),
            )->filter(static fn (?int $id): bool => $id !== null)->values()->all();

            return [
                'kind' => 'group',
                'title' => $item->name,
                'description' => FattureInCloudQuoteLogic::descriptionWithoutReferences($description),
                'net_price' => self::decimal($item->netPrice),
                'quantity' => self::decimal($item->quantity),
                'measure' => $item->measure ?? '',
                'discount' => self::decimal($item->discount),
                'vat_type_id' => $item->vatTypeId ?? '',
                'product_id' => $item->productId,
                'product_code' => $item->code,
                'finding_ids' => $findingIds,
                'solution_ids' => [],
                'unmatched' => $findingIds === [] && preg_match('/F-[0-9]{6}/', $description) === 1,
            ];
        }, $document->items);
    }

    private static function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
