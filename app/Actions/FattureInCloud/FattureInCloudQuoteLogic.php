<?php

declare(strict_types=1);

namespace App\Actions\FattureInCloud;

use App\Enums\EstimateType;
use App\Models\FindingSolution;
use InvalidArgumentException;

final class FattureInCloudQuoteLogic
{
    public static function findingReference(int $findingId): string
    {
        if ($findingId < 1 || $findingId > 999999) {
            throw new InvalidArgumentException('Finding ID cannot be represented by the FIC reference contract.');
        }

        return sprintf('F-%06d', $findingId);
    }

    public static function marker(int $assessmentId, int $version): string
    {
        if ($assessmentId < 1 || $version < 1) {
            throw new InvalidArgumentException('Assessment ID and quote version must be positive.');
        }

        return sprintf('[ASSESTME assessment=%d version=%d]', $assessmentId, $version);
    }

    /** @return array{assessment_id: int, version: int}|null */
    public static function parseMarker(string $marker): ?array
    {
        if (preg_match('/\A\[ASSESTME assessment=([1-9][0-9]*) version=([1-9][0-9]*)\]\z/', $marker, $matches) !== 1) {
            return null;
        }

        return ['assessment_id' => (int) $matches[1], 'version' => (int) $matches[2]];
    }

    /** @param list<FindingSolution> $solutions */
    public static function compatibleEstimateSum(array $solutions): ?float
    {
        if ($solutions === []) {
            return null;
        }
        $currency = null;
        $frequency = null;
        $sum = 0.0;
        foreach ($solutions as $solution) {
            if ($solution->estimate_type !== EstimateType::Approximate || $solution->amount_min === null
                || $solution->currency_code === null) {
                return null;
            }
            $currency ??= $solution->currency_code;
            $frequency ??= $solution->billing_frequency->value;
            if ($currency !== $solution->currency_code || $frequency !== $solution->billing_frequency->value) {
                return null;
            }
            $sum += (float) $solution->amount_min;
        }

        return is_finite($sum) ? round($sum, 2) : null;
    }

    /** @param list<int> $findingIds */
    public static function descriptionWithReferences(string $description, array $findingIds): string
    {
        $description = self::descriptionWithoutReferences($description);
        $references = array_map(self::findingReference(...), array_values(array_unique($findingIds)));
        sort($references, SORT_STRING);
        if ($references === []) {
            return trim($description);
        }

        $referenceLine = 'Riferimenti AssestMe: '.implode(', ', $references);

        return trim($description) === '' ? $referenceLine : trim($description)."\n\n".$referenceLine;
    }

    public static function descriptionWithoutReferences(string $description): string
    {
        return trim((string) preg_replace(
            '/(?:\A|\n{2})Riferimenti AssestMe: F-[0-9]{6}(?:, F-[0-9]{6})*\s*\z/',
            '',
            $description,
        ));
    }

    /** @param list<array{net_price: string, quantity: string, discount: string}> $rows */
    public static function transientNetTotal(array $rows): ?float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            if (! is_numeric($row['net_price']) || ! is_numeric($row['quantity']) || ! is_numeric($row['discount'])) {
                return null;
            }

            $netPrice = (float) $row['net_price'];
            $quantity = (float) $row['quantity'];
            $discount = (float) $row['discount'];
            if (! is_finite($netPrice) || ! is_finite($quantity) || ! is_finite($discount)
                || $netPrice < 0 || $quantity <= 0 || $discount < 0 || $discount > 100) {
                return null;
            }

            $total += $netPrice * $quantity * (1 - ($discount / 100));
        }

        return is_finite($total) ? round($total, 2) : null;
    }
}
