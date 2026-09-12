<?php

declare(strict_types=1);

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Models\Assessment;
use App\Models\Finding;
use App\Models\FindingTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

it('converts persisted exact estimates to the single Stima type', function (): void {
    $finding = Finding::factory()->for(Assessment::factory())->create();
    $findingSolution = $finding->solutions()->create([
        'external_key' => 'legacy-finding-estimate',
        'title' => 'Soluzione Finding',
        'description' => 'Descrizione soluzione Finding.',
        'estimate_type' => EstimateType::Approximate,
        'amount_min' => '100.00',
        'currency_code' => 'EUR',
        'billing_frequency' => BillingFrequency::OneOff,
        'sort_order' => 1,
    ]);
    $template = FindingTemplate::factory()->create();
    $templateSolution = $template->solutions()->create([
        'external_id' => 'legacy-template-estimate',
        'title' => 'Soluzione template',
        'description' => 'Descrizione soluzione template.',
        'estimate_type' => EstimateType::Approximate,
        'amount_min' => '200.00',
        'currency_code' => 'EUR',
        'billing_frequency' => BillingFrequency::OneOff,
        'is_recommended' => true,
        'sort_order' => 1,
    ]);

    DB::table('finding_solutions')->where('id', $findingSolution->getKey())->update(['estimate_type' => 'exact']);
    DB::table('finding_template_solutions')->where('id', $templateSolution->getKey())->update(['estimate_type' => 'exact']);

    $migration = require database_path('migrations/2026_09_09_000023_consolidate_exact_estimates.php');
    assert($migration instanceof Migration);
    $migration->up();

    expect(DB::table('finding_solutions')->where('id', $findingSolution->getKey())->value('estimate_type'))
        ->toBe(EstimateType::Approximate->value)
        ->and(DB::table('finding_template_solutions')->where('id', $templateSolution->getKey())->value('estimate_type'))
        ->toBe(EstimateType::Approximate->value)
        ->and((float) DB::table('finding_solutions')->where('id', $findingSolution->getKey())->value('amount_min'))
        ->toBe(100.0);
});
