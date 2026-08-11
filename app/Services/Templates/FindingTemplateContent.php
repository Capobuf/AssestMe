<?php

declare(strict_types=1);

namespace App\Services\Templates;

use App\Actions\Risk\CalculateFindingPriority;
use App\Models\Finding;
use App\Models\FindingSolution;
use App\Models\FindingTemplate;
use App\Models\FindingTemplateSolution;
use App\Models\PriorityLevel;
use App\Services\Risk\ActiveRiskProfileResolver;
use Illuminate\Support\Collection;
use JsonException;
use Normalizer;
use RuntimeException;

final class FindingTemplateContent
{
    public function __construct(
        private readonly CalculateFindingPriority $calculateFindingPriority,
        private readonly ActiveRiskProfileResolver $activeRiskProfileResolver,
    ) {}

    public function fingerprint(FindingTemplate $template): string
    {
        return hash('sha256', $this->canonicalJson($this->templateProjection($template, semantic: false)));
    }

    public function findingSemanticSignature(Finding $finding): string
    {
        return hash('sha256', $this->canonicalJson($this->findingProjection($finding, semantic: true)));
    }

    public function templateSemanticSignature(FindingTemplate $template): string
    {
        return hash('sha256', $this->canonicalJson($this->templateProjection($template, semantic: true)));
    }

    public function isSemanticallyIdentical(Finding $finding, FindingTemplate $template): bool
    {
        return hash_equals(
            $this->findingSemanticSignature($finding),
            $this->templateSemanticSignature($template),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function templatePayload(Finding $finding, ?FindingTemplate $source = null): array
    {
        $finding->loadMissing('solutions');
        $priority = $this->derivedPriority($finding);

        if ($source === null) {
            $this->activeRiskProfileResolver->assertSelectableClassification([
                'consequence' => $finding->consequence_level_id,
                'likelihood' => $finding->likelihood_level_id,
                'priority' => $priority?->getKey() === null ? null : (int) $priority->getKey(),
            ], 'priority_level_id');
        }

        $sourceSolutions = $source?->solutions()->withTrashed()->get()->keyBy('external_id') ?? collect();

        return [
            'title' => $finding->title,
            'category_id' => $finding->category_id,
            'problem' => $finding->problem,
            'entrepreneur_notes' => $finding->entrepreneur_notes,
            'technical_notes' => $finding->technical_notes,
            'default_scope_type' => $finding->scope_type->value,
            'default_scope_description' => $finding->scope_description,
            'default_consequence_level_id' => $finding->consequence_level_id,
            'default_likelihood_level_id' => $finding->likelihood_level_id,
            'default_priority_level_id' => $priority?->getKey(),
            'priority_rationale' => $finding->priority_is_overridden ? null : $finding->priority_rationale,
            'is_enabled' => $source === null ? true : $source->is_enabled,
            'solutions' => $this->orderedFindingSolutions($finding)->map(
                function (FindingSolution $solution) use ($finding, $sourceSolutions): array {
                    $existing = $solution->external_key === null
                        ? null
                        : $sourceSolutions->get($solution->external_key);

                    return array_filter([
                        'id' => $existing instanceof FindingTemplateSolution ? $existing->getKey() : null,
                        'external_id' => $existing instanceof FindingTemplateSolution ? $existing->external_id : null,
                        'title' => $solution->title,
                        'description' => $solution->description,
                        'comparison_notes' => $solution->comparison_notes,
                        'effort_level_id' => $solution->effort_level_id,
                        'effort_notes' => $solution->effort_notes,
                        'estimate_type' => $solution->estimate_type->value,
                        'amount_min' => $solution->amount_min,
                        'amount_max' => $solution->amount_max,
                        'currency_code' => $solution->currency_code,
                        'billing_frequency' => $solution->billing_frequency->value,
                        'custom_billing_frequency' => $solution->custom_billing_frequency,
                        'estimate_notes' => $solution->estimate_notes,
                        'is_recommended' => (int) $finding->recommended_solution_id === (int) $solution->getKey(),
                        'sort_order' => $solution->sort_order,
                    ], static fn (mixed $value, string $key): bool => ! in_array($key, ['id', 'external_id'], true) || $value !== null, ARRAY_FILTER_USE_BOTH);
                },
            )->values()->all(),
        ];
    }

    /**
     * @return array{fields:array<string,bool>,solutions:array{added:int,changed:int,removed:int}}
     */
    public function diff(Finding $finding, FindingTemplate $template): array
    {
        $findingProjection = $this->findingProjection($finding, semantic: false);
        $templateProjection = $this->templateProjection($template, semantic: false);
        $fields = [];

        foreach (array_keys($findingProjection) as $field) {
            if ($field === 'solutions') {
                continue;
            }
            $fields[$field] = $findingProjection[$field] !== $templateProjection[$field];
        }

        $findingSolutions = $this->orderedFindingSolutions($finding);
        $templateSolutions = $template->solutions()->get();
        $templateByExternalId = $templateSolutions->keyBy('external_id');
        $matchedTemplateIds = [];
        $added = 0;
        $changed = 0;

        foreach ($findingSolutions as $solution) {
            $templateSolution = $solution->external_key === null
                ? null
                : $templateByExternalId->get($solution->external_key);
            if (! $templateSolution instanceof FindingTemplateSolution) {
                $added++;

                continue;
            }

            $matchedTemplateIds[] = (int) $templateSolution->getKey();
            if ($this->findingSolutionProjection($solution, $finding, semantic: false)
                !== $this->templateSolutionProjection($templateSolution, semantic: false)) {
                $changed++;
            }
        }

        return [
            'fields' => $fields,
            'solutions' => [
                'added' => $added,
                'changed' => $changed,
                'removed' => $templateSolutions->whereNotIn('id', $matchedTemplateIds)->count(),
            ],
        ];
    }

    /** @param array{fields:array<string,bool>,solutions:array{added:int,changed:int,removed:int}} $diff */
    public function hasDifferences(array $diff): bool
    {
        return in_array(true, $diff['fields'], true)
            || array_sum($diff['solutions']) > 0;
    }

    /**
     * @return array<int, string> FindingSolution ID => FindingTemplateSolution external ID
     */
    public function matchSemanticallyIdenticalSolutions(Finding $finding, FindingTemplate $template): array
    {
        $findingGroups = $this->orderedFindingSolutions($finding)->groupBy(
            fn (FindingSolution $solution): string => $this->canonicalJson(
                $this->findingSolutionProjection($solution, $finding, semantic: true),
            ),
        );
        $templateGroups = $template->solutions()->get()->sortBy([
            ['sort_order', 'asc'],
            ['external_id', 'asc'],
            ['id', 'asc'],
        ])->groupBy(
            fn (FindingTemplateSolution $solution): string => $this->canonicalJson(
                $this->templateSolutionProjection($solution, semantic: true),
            ),
        );
        $map = [];

        foreach ($findingGroups as $signature => $findingSolutions) {
            $templateSolutions = $templateGroups->get($signature, collect())->values();
            $orderedFindings = $findingSolutions->sortBy('id')->values();
            if ($orderedFindings->count() !== $templateSolutions->count()) {
                throw new RuntimeException('Semantically identical templates must have a complete solution mapping.');
            }
            foreach ($orderedFindings as $index => $solution) {
                $templateSolution = $templateSolutions->get($index);
                if (! $templateSolution instanceof FindingTemplateSolution) {
                    throw new RuntimeException('Semantically identical template solution mapping is incomplete.');
                }
                $map[(int) $solution->getKey()] = $templateSolution->external_id;
            }
        }

        if (count($map) !== $this->orderedFindingSolutions($finding)->count()) {
            throw new RuntimeException('Semantically identical template solution mapping is incomplete.');
        }

        return $map;
    }

    /** @return Collection<int, FindingSolution> */
    public function orderedFindingSolutions(Finding $finding): Collection
    {
        $finding->loadMissing('solutions');

        return $finding->solutions->sortBy([
            ['sort_order', 'asc'],
            ['id', 'asc'],
        ])->values();
    }

    /** @return array<string, mixed> */
    private function findingProjection(Finding $finding, bool $semantic): array
    {
        $finding->loadMissing('solutions');
        $priority = $this->derivedPriority($finding);
        $solutions = $this->orderedFindingSolutions($finding)->map(
            fn (FindingSolution $solution): array => $this->findingSolutionProjection($solution, $finding, $semantic),
        )->all();
        if ($semantic) {
            $solutions = $this->sortSemanticSolutions($solutions);
        }

        return [
            'category_id' => $finding->category_id,
            'title' => $this->text($finding->title, $semantic),
            'problem' => $this->text($finding->problem, $semantic),
            'entrepreneur_notes' => $this->text($finding->entrepreneur_notes, $semantic),
            'technical_notes' => $this->text($finding->technical_notes, $semantic),
            'scope_type' => $finding->scope_type->value,
            'scope_description' => $this->text($finding->scope_description, $semantic),
            'consequence_level_id' => $finding->consequence_level_id,
            'likelihood_level_id' => $finding->likelihood_level_id,
            'priority_level_id' => $priority?->getKey(),
            'priority_rationale' => $this->text(
                $finding->priority_is_overridden ? null : $finding->priority_rationale,
                $semantic,
            ),
            'solutions' => $solutions,
        ];
    }

    /** @return array<string, mixed> */
    private function templateProjection(FindingTemplate $template, bool $semantic): array
    {
        $template->loadMissing('solutions');
        $solutions = $template->solutions->sortBy([
            ['sort_order', 'asc'],
            ['external_id', 'asc'],
            ['id', 'asc'],
        ])->values()->map(
            fn (FindingTemplateSolution $solution): array => $this->templateSolutionProjection($solution, $semantic),
        )->all();
        if ($semantic) {
            $solutions = $this->sortSemanticSolutions($solutions);
        }

        return [
            'category_id' => $template->category_id,
            'title' => $this->text($template->title, $semantic),
            'problem' => $this->text($template->problem, $semantic),
            'entrepreneur_notes' => $this->text($template->entrepreneur_notes, $semantic),
            'technical_notes' => $this->text($template->technical_notes, $semantic),
            'scope_type' => $template->default_scope_type->value,
            'scope_description' => $this->text($template->default_scope_description, $semantic),
            'consequence_level_id' => $template->default_consequence_level_id,
            'likelihood_level_id' => $template->default_likelihood_level_id,
            'priority_level_id' => $template->default_priority_level_id,
            'priority_rationale' => $this->text($template->priority_rationale, $semantic),
            'solutions' => $solutions,
        ];
    }

    /**
     * Operational identifiers must not decide semantic equality when two solutions share a sort position.
     *
     * @param  list<array<string, mixed>>  $solutions
     * @return list<array<string, mixed>>
     */
    private function sortSemanticSolutions(array $solutions): array
    {
        usort($solutions, fn (array $left, array $right): int => [
            $left['sort_order'],
            $this->canonicalJson($left),
        ] <=> [
            $right['sort_order'],
            $this->canonicalJson($right),
        ]);

        return $solutions;
    }

    /** @return array<string, mixed> */
    private function findingSolutionProjection(FindingSolution $solution, Finding $finding, bool $semantic): array
    {
        return $this->solutionProjection([
            'external_id' => $solution->external_key,
            'title' => $solution->title,
            'description' => $solution->description,
            'comparison_notes' => $solution->comparison_notes,
            'effort_level_id' => $solution->effort_level_id,
            'effort_notes' => $solution->effort_notes,
            'estimate_type' => $solution->estimate_type->value,
            'amount_min' => $solution->amount_min,
            'amount_max' => $solution->amount_max,
            'currency_code' => $solution->currency_code,
            'billing_frequency' => $solution->billing_frequency->value,
            'custom_billing_frequency' => $solution->custom_billing_frequency,
            'estimate_notes' => $solution->estimate_notes,
            'is_recommended' => (int) $finding->recommended_solution_id === (int) $solution->getKey(),
            'sort_order' => $solution->sort_order,
        ], $semantic);
    }

    /** @return array<string, mixed> */
    private function templateSolutionProjection(FindingTemplateSolution $solution, bool $semantic): array
    {
        return $this->solutionProjection([
            'external_id' => $solution->external_id,
            'title' => $solution->title,
            'description' => $solution->description,
            'comparison_notes' => $solution->comparison_notes,
            'effort_level_id' => $solution->effort_level_id,
            'effort_notes' => $solution->effort_notes,
            'estimate_type' => $solution->estimate_type->value,
            'amount_min' => $solution->amount_min,
            'amount_max' => $solution->amount_max,
            'currency_code' => $solution->currency_code,
            'billing_frequency' => $solution->billing_frequency->value,
            'custom_billing_frequency' => $solution->custom_billing_frequency,
            'estimate_notes' => $solution->estimate_notes,
            'is_recommended' => $solution->is_recommended,
            'sort_order' => $solution->sort_order,
        ], $semantic);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function solutionProjection(array $values, bool $semantic): array
    {
        $projection = [
            'title' => $this->text($values['title'], $semantic),
            'description' => $this->text($values['description'], $semantic),
            'comparison_notes' => $this->text($values['comparison_notes'], $semantic),
            'effort_level_id' => $values['effort_level_id'],
            'effort_notes' => $this->text($values['effort_notes'], $semantic),
            'estimate_type' => $values['estimate_type'],
            'amount_min' => $this->decimal($values['amount_min']),
            'amount_max' => $this->decimal($values['amount_max']),
            'currency_code' => $values['currency_code'],
            'billing_frequency' => $values['billing_frequency'],
            'custom_billing_frequency' => $this->text($values['custom_billing_frequency'], $semantic),
            'estimate_notes' => $this->text($values['estimate_notes'], $semantic),
            'is_recommended' => (bool) $values['is_recommended'],
            'sort_order' => (int) $values['sort_order'],
        ];

        return $semantic ? $projection : ['external_id' => $values['external_id'], ...$projection];
    }

    private function derivedPriority(Finding $finding): ?PriorityLevel
    {
        if ($finding->consequence_level_id === null || $finding->likelihood_level_id === null) {
            return null;
        }

        return ($this->calculateFindingPriority)(
            $finding->consequenceLevel()->firstOrFail(),
            $finding->likelihoodLevel()->firstOrFail(),
        );
    }

    private function text(mixed $value, bool $semantic): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = str_replace(["\r\n", "\r"], "\n", (string) $value);
        $form = $semantic ? Normalizer::FORM_KC : Normalizer::FORM_C;
        $normalized = Normalizer::normalize($text, $form) ?: $text;
        if (! $semantic) {
            return $normalized;
        }

        $normalized = mb_strtolower($normalized);
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;

        return trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);
    }

    private function decimal(mixed $value): ?string
    {
        return $value === null ? null : number_format((float) $value, 2, '.', '');
    }

    /** @param array<string, mixed> $value */
    private function canonicalJson(array $value): string
    {
        try {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Reusable template content cannot be canonicalized.', previous: $exception);
        }
    }
}
