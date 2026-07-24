<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Actions\Categories\SaveCategory;
use App\Models\Category;
use App\Models\EffortLevel;
use App\Models\FindingTemplate;
use App\Models\FindingTemplateSolution;
use App\Models\RiskProfile;
use App\Services\Reporting\EditorialLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use Normalizer;
use Opis\JsonSchema\Validator;

final class ImportFindingTemplates
{
    /** @return array{created:int,replaced:int,skipped:int} */
    public function __invoke(string $json, string $conflictMode): array
    {
        if (! in_array($conflictMode, ['replace', 'skip'], true)) {
            throw ValidationException::withMessages(['conflict_mode' => __('assestme.templates.errors.conflict_mode')]);
        }

        $document = $this->validatedDocument($json);

        return DB::transaction(function () use ($document, $conflictMode): array {
            $result = ['created' => 0, 'replaced' => 0, 'skipped' => 0];

            foreach ($document['templates'] as $row) {
                $externalId = (string) $row['external_id'];
                $template = FindingTemplate::withTrashed()->where('external_id', $externalId)->first();

                if ($template !== null && $conflictMode === 'skip') {
                    $result['skipped']++;

                    continue;
                }

                $created = $template === null;
                $template ??= new FindingTemplate;
                $category = $this->resolveCategory((string) $row['category']);
                $profile = RiskProfile::query()->where('is_default', true)->where('is_enabled', true)->firstOrFail();

                $template->fill([
                    'external_id' => $externalId,
                    'title' => $row['title'],
                    'category_id' => $category->getKey(),
                    'problem' => $row['problem'],
                    'entrepreneur_notes' => $row['entrepreneur_notes'],
                    'technical_notes' => $row['technical_notes'],
                    'default_scope_type' => $row['default_scope_type'],
                    'default_scope_description' => $row['default_scope_description'],
                    'default_consequence_level_id' => $this->levelId($profile, 'consequenceLevels', $row['consequence']),
                    'default_likelihood_level_id' => $this->levelId($profile, 'likelihoodLevels', $row['likelihood']),
                    'default_priority_level_id' => $this->levelId($profile, 'priorityLevels', $row['priority']),
                    'priority_rationale' => $row['priority_rationale'],
                    'is_enabled' => $row['active'],
                ]);
                $template->save();
                $template->restore();

                $this->replaceSolutions($template, $row['solutions']);
                $result[$created ? 'created' : 'replaced']++;
            }

            return $result;
        });
    }

    /** @return list<array{external_id:string,status:string,differences:list<string>}> */
    public function preview(string $json): array
    {
        $document = $this->validatedDocument($json);
        $existing = FindingTemplate::withTrashed()
            ->with(['category', 'solutions.effortLevel', 'defaultConsequenceLevel', 'defaultLikelihoodLevel', 'defaultPriorityLevel'])
            ->whereIn('external_id', array_column($document['templates'], 'external_id'))
            ->get()
            ->keyBy('external_id');

        return array_map(function (array $row) use ($existing): array {
            $template = $existing->get($row['external_id']);
            if (! $template instanceof FindingTemplate) {
                return ['external_id' => (string) $row['external_id'], 'status' => 'create', 'differences' => []];
            }

            $differences = $this->differences($template, $row);

            return [
                'external_id' => (string) $row['external_id'],
                'status' => $differences === [] ? 'unchanged' : 'replace',
                'differences' => $differences,
            ];
        }, $document['templates']);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private function differences(FindingTemplate $template, array $row): array
    {
        $current = [
            'title' => $template->title,
            'category' => $template->category->name,
            'problem' => $template->problem,
            'entrepreneur_notes' => $template->entrepreneur_notes,
            'technical_notes' => $template->technical_notes,
            'default_scope_type' => $template->default_scope_type->value,
            'default_scope_description' => $template->default_scope_description,
            'consequence' => $template->defaultConsequenceLevel?->code,
            'likelihood' => $template->defaultLikelihoodLevel?->code,
            'priority' => $template->defaultPriorityLevel?->code,
            'priority_rationale' => $template->priority_rationale,
            'active' => $template->is_enabled,
        ];
        $incoming = collect($row)->only(array_keys($current))->all();
        $differences = [];

        foreach ($current as $field => $value) {
            $incomingValue = $incoming[$field] ?? null;
            if ($value !== $incomingValue) {
                $differences[] = $field;
            }
        }

        $currentSolutions = $template->solutions->map(static fn (FindingTemplateSolution $solution): array => [
            'external_id' => $solution->external_id,
            'title' => $solution->title,
            'description' => $solution->description,
            'comparison_notes' => $solution->comparison_notes,
            'effort' => $solution->effortLevel?->code,
            'effort_notes' => $solution->effort_notes,
            'estimate_type' => $solution->estimate_type->value,
            'amount_min' => $solution->amount_min === null ? null : (float) $solution->amount_min,
            'amount_max' => $solution->amount_max === null ? null : (float) $solution->amount_max,
            'currency_code' => $solution->currency_code,
            'billing_frequency' => $solution->billing_frequency->value,
            'custom_billing_frequency' => $solution->custom_billing_frequency,
            'estimate_notes' => $solution->estimate_notes,
            'recommended' => $solution->is_recommended,
            'sort_order' => $solution->sort_order,
        ])->values()->all();
        if ($currentSolutions !== $row['solutions']) {
            $differences[] = 'solutions';
        }

        return $differences;
    }

    /** @return array{schema_version:int,templates:list<array<string, mixed>>} */
    private function validatedDocument(string $json): array
    {
        try {
            $object = json_decode($json, flags: JSON_THROW_ON_ERROR);
            $document = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['file' => __('assestme.templates.errors.invalid_json')]);
        }

        $schema = json_decode((string) file_get_contents(base_path('schemas/finding-template.schema.json')));
        if (! (new Validator)->validate($object, $schema)->isValid() || ! is_array($document)) {
            throw ValidationException::withMessages(['file' => __('assestme.templates.errors.invalid_schema')]);
        }

        /** @var array{schema_version:int,templates:list<array<string, mixed>>} $document */
        $templateIds = array_column($document['templates'], 'external_id');
        if (count($templateIds) !== count(array_unique($templateIds))) {
            throw ValidationException::withMessages(['file' => __('assestme.templates.errors.duplicate_template')]);
        }

        foreach ($document['templates'] as $index => $template) {
            $violations = EditorialLimits::violationsForValues(
                fields: [
                    'title' => $template['title'],
                    'problem' => $template['problem'],
                    'entrepreneur_notes' => $template['entrepreneur_notes'],
                    'technical_notes' => $template['technical_notes'],
                ],
                solutions: $template['solutions'],
            );
            if ($violations !== []) {
                throw ValidationException::withMessages([
                    "templates.{$index}.{$violations[0]['field']}" => __('assestme.findings.errors.editorial_limit', $violations[0]),
                ]);
            }

            $solutionIds = array_column($template['solutions'], 'external_id');
            if (count($solutionIds) !== count(array_unique($solutionIds))) {
                throw ValidationException::withMessages(["templates.{$index}.solutions" => __('assestme.templates.errors.duplicate_solution')]);
            }

            foreach ($template['solutions'] as $solutionIndex => $solution) {
                if ($solution['estimate_type'] === 'range' && (float) $solution['amount_min'] > (float) $solution['amount_max']) {
                    throw ValidationException::withMessages([
                        "templates.{$index}.solutions.{$solutionIndex}.amount_max" => __('assestme.templates.errors.invalid_range'),
                    ]);
                }
            }
        }

        return $document;
    }

    private function resolveCategory(string $name): Category
    {
        $normalized = $this->normalizeName($name);
        $category = Category::withTrashed()->get()->first(fn (Category $item): bool => $this->normalizeName($item->name) === $normalized);

        return $category ?? app(SaveCategory::class)->handle(null, [
            'name' => $name, 'sort_order' => Category::query()->max('sort_order') + 1, 'is_enabled' => true,
        ]);
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(Normalizer::normalize(trim($name), Normalizer::FORM_C) ?: trim($name));
    }

    private function levelId(RiskProfile $profile, string $relation, mixed $code): ?int
    {
        if ($code === null) {
            return null;
        }

        /** @var int $id */
        $id = $profile->{$relation}()->where('code', $code)->value('id');

        return $id;
    }

    /** @param list<array<string, mixed>> $rows */
    private function replaceSolutions(FindingTemplate $template, array $rows): void
    {
        $externalIds = [];

        foreach ($rows as $row) {
            $externalId = (string) $row['external_id'];
            $externalIds[] = $externalId;
            $solution = FindingTemplateSolution::withTrashed()
                ->where('finding_template_id', $template->getKey())
                ->where('external_id', $externalId)
                ->first() ?? new FindingTemplateSolution(['finding_template_id' => $template->getKey()]);
            $effortId = $row['effort'] === null ? null : EffortLevel::query()->where('code', $row['effort'])->value('id');
            $solution->fill([
                'external_id' => $externalId, 'title' => $row['title'], 'description' => $row['description'],
                'comparison_notes' => $row['comparison_notes'], 'effort_level_id' => $effortId,
                'effort_notes' => $row['effort_notes'], 'estimate_type' => $row['estimate_type'],
                'amount_min' => $row['amount_min'], 'amount_max' => $row['amount_max'],
                'currency_code' => $row['currency_code'], 'billing_frequency' => $row['billing_frequency'],
                'custom_billing_frequency' => $row['custom_billing_frequency'], 'estimate_notes' => $row['estimate_notes'],
                'is_recommended' => $row['recommended'], 'sort_order' => $row['sort_order'],
            ]);
            $solution->save();
            $solution->restore();
        }

        $template->solutions()->whereNotIn('external_id', $externalIds)->delete();
    }
}
