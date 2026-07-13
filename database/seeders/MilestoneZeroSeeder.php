<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EffortLevel;
use App\Enums\EstimateType;
use App\Enums\FindingPriority;
use App\Models\Assessment;
use App\Models\Finding;
use Illuminate\Database\Seeder;
use RuntimeException;

final class MilestoneZeroSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction() && ! (bool) $this->command->option('force')) {
            throw new RuntimeException('MilestoneZeroSeeder is disabled in production without --force.');
        }

        $assessment = Assessment::query()->firstOrCreate(
            ['title' => 'Assessment IT — Proof Milestone 0'],
            ['assessment_date' => today(), 'lock_version' => 0],
        );

        if ($assessment->findings()->exists()) {
            return;
        }

        $longText = implode("\n\n", array_fill(
            0,
            6,
            'La configurazione attuale presenta un rischio operativo che richiede verifica, pianificazione e una correzione documentata.',
        ));

        foreach (range(1, 50) as $position) {
            Finding::factory()->for($assessment)->create([
                'title' => "Finding {$position}",
                'problem' => $position === 1 ? $longText : "Problema rilevato alla riga {$position}.\nSeconda riga preservata.",
                'entrepreneur_notes' => "Impatto operativo per l’organizzazione.\nPriorità da condividere.",
                'recommended_solution_summary' => "Intervento raccomandato {$position}.\nVerificare l’esito dopo l’implementazione.",
                'priority' => FindingPriority::cases()[($position - 1) % count(FindingPriority::cases())],
                'effort' => EffortLevel::cases()[($position - 1) % count(EffortLevel::cases())],
                'estimate_type' => EstimateType::cases()[($position - 1) % count(EstimateType::cases())],
                'sort_order' => $position,
            ]);
        }
    }
}
