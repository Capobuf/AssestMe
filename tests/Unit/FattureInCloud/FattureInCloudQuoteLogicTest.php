<?php

declare(strict_types=1);

use App\Actions\FattureInCloud\FattureInCloudQuoteLogic;
use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Models\FindingSolution;

it('generates exact references and markers and rejects non-exact marker grammar', function (): void {
    expect(FattureInCloudQuoteLogic::findingReference(42))->toBe('F-000042')
        ->and(FattureInCloudQuoteLogic::marker(7, 3))->toBe('[ASSESTME assessment=7 version=3]')
        ->and(FattureInCloudQuoteLogic::parseMarker('[ASSESTME assessment=7 version=3]'))
        ->toBe(['assessment_id' => 7, 'version' => 3])
        ->and(FattureInCloudQuoteLogic::parseMarker('x [ASSESTME assessment=7 version=3]'))->toBeNull()
        ->and(FattureInCloudQuoteLogic::parseMarker('[assestme assessment=7 version=3]'))->toBeNull();
});

it('suggests only a compatible sum of exact estimates', function (): void {
    $first = ficExactSolution(100);
    $second = ficExactSolution(25.5);

    expect(FattureInCloudQuoteLogic::compatibleExactSum([$first, $second]))->toBe(125.5);

    $second->estimate_type = EstimateType::Range;
    expect(FattureInCloudQuoteLogic::compatibleExactSum([$first, $second]))->toBeNull();

    $second->estimate_type = EstimateType::Exact;
    $second->currency_code = 'USD';
    expect(FattureInCloudQuoteLogic::compatibleExactSum([$first, $second]))->toBeNull();
});

it('adds sorted unique Finding references to one row description', function (): void {
    expect(FattureInCloudQuoteLogic::descriptionWithReferences('Intervento', [42, 3, 42]))
        ->toBe("Intervento\n\nRiferimenti AssestMe: F-000003, F-000042");
});

function ficExactSolution(float $amount): FindingSolution
{
    return new FindingSolution([
        'title' => 'Soluzione',
        'description' => 'Descrizione',
        'estimate_type' => EstimateType::Exact,
        'amount_min' => $amount,
        'currency_code' => 'EUR',
        'billing_frequency' => BillingFrequency::OneOff,
        'sort_order' => 1,
    ]);
}
