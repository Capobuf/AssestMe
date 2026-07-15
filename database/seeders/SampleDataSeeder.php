<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\Category;
use App\Models\Client;
use App\Models\ConsequenceLevel;
use App\Models\EffortLevel;
use App\Models\Finding;
use App\Models\LikelihoodLevel;
use App\Models\RiskMatrixEntry;
use Illuminate\Database\Seeder;
use RuntimeException;

final class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! (bool) $this->command->option('force')) {
            throw new RuntimeException('SampleDataSeeder is disabled in production without --force.');
        }

        $client = Client::query()->firstOrCreate(
            ['legal_name' => 'Cliente di prova AssestMe'],
            ['country' => 'IT'],
        );

        $assessment = Assessment::query()->firstOrCreate(
            ['title' => 'Assessment IT — Esempio'],
            ['client_id' => $client->getKey(), 'assessment_date' => today(), 'lock_version' => 0],
        );

        if ($assessment->findings()->exists()) {
            return;
        }

        $longText = implode("\n\n", array_fill(
            0,
            6,
            'La configurazione attuale presenta un rischio operativo che richiede verifica, pianificazione e una correzione documentata.',
        ));
        $category = Category::query()->where('slug', 'sicurezza')->firstOrFail();
        $consequences = ConsequenceLevel::query()->orderBy('score')->get();
        $likelihoods = LikelihoodLevel::query()->orderBy('score')->get();
        $efforts = EffortLevel::query()->orderBy('sort_order')->get();

        foreach (range(1, 50) as $position) {
            $consequence = $consequences[($position - 1) % $consequences->count()];
            $likelihood = $likelihoods[($position - 1) % $likelihoods->count()];
            $priorityId = RiskMatrixEntry::query()
                ->where('consequence_level_id', $consequence->id)
                ->where('likelihood_level_id', $likelihood->id)
                ->value('priority_level_id');
            $finding = Finding::factory()->for($assessment)->create([
                'title' => "Finding {$position}",
                'category_id' => $category->id,
                'problem' => $position === 1 ? $longText : "Problema rilevato alla riga {$position}.\nSeconda riga preservata.",
                'entrepreneur_notes' => "Impatto operativo per l’organizzazione.\nPriorità da condividere.",
                'scope_type' => ScopeType::Organization,
                'consequence_level_id' => $consequence->id,
                'likelihood_level_id' => $likelihood->id,
                'priority_level_id' => $priorityId,
                'sort_order' => $position,
            ]);
            $solution = $finding->solutions()->create([
                'title' => "Intervento raccomandato {$position}",
                'description' => "Intervento raccomandato {$position}.\nVerificare l’esito dopo l’implementazione.",
                'effort_level_id' => $efforts[($position - 1) % $efforts->count()]->id,
                'estimate_type' => EstimateType::RequiresQuote,
                'billing_frequency' => BillingFrequency::OneOff,
                'estimate_notes' => 'Richiedere una quotazione dopo l’analisi tecnica.',
                'sort_order' => 1,
            ]);
            $solution->update(['external_key' => "manual-{$solution->id}"]);
            $finding->update(['recommended_solution_id' => $solution->id]);
        }
    }
}
