<?php

declare(strict_types=1);

use App\Actions\Assessments\AssessFindingCompleteness;
use App\Actions\Assessments\CompleteAssessment;
use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Templates\ExportFindingTemplates;
use App\Actions\Templates\ImportFindingTemplates;
use App\Enums\AssessmentStatus;
use App\Enums\EstimateType;
use App\Enums\ScopeType;
use App\Models\Assessment;
use App\Models\FindingTemplate;
use App\Models\FindingTemplateSolution;
use App\Settings\GeneralSettings;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
});

it('imports every valid template atomically and exports a data-equivalent document', function (): void {
    $json = (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json'));
    $result = app(ImportFindingTemplates::class)($json, 'replace');

    expect($result)->toBe(['created' => 5, 'replaced' => 0, 'skipped' => 0])
        ->and(FindingTemplate::query()->count())->toBe(5)
        ->and(FindingTemplateSolution::query()->count())->toBe(11)
        ->and(FindingTemplate::query()->whereHas('solutions', fn ($query) => $query->where('is_recommended', true))->count())->toBe(5);

    $export = app(ExportFindingTemplates::class)();
    expect(json_decode($export, true, flags: JSON_THROW_ON_ERROR)['schema_version'])->toBe(2);
    $roundTrip = app(ImportFindingTemplates::class)($export, 'replace');

    expect($roundTrip)->toBe(['created' => 0, 'replaced' => 5, 'skipped' => 0])
        ->and(json_decode(app(ExportFindingTemplates::class)(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(json_decode($export, true, flags: JSON_THROW_ON_ERROR));
});

it('keeps assets optional for a finding copied from an imported selected asset template', function (): void {
    $json = (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json'));
    app(ImportFindingTemplates::class)($json, 'replace');
    $template = FindingTemplate::query()
        ->where('external_id', 'fixture.non-monetary')
        ->sole();
    $assessment = Assessment::factory()->create();

    $finding = app(CopyTemplateToAssessment::class)($assessment, $template);
    $incompleteIds = app(AssessFindingCompleteness::class)
        ->applyIncompleteFilter($assessment->findings()->getQuery())
        ->pluck('id')
        ->all();
    $completed = app(CompleteAssessment::class)($assessment->fresh());

    expect($finding->scope_type)->toBe(ScopeType::SelectedAssets)
        ->and($finding->assets)->toHaveCount(0)
        ->and(app(AssessFindingCompleteness::class)($finding))->toBe([])
        ->and($incompleteIds)->toBe([])
        ->and($completed->status)->toBe(AssessmentStatus::Completed);
});

it('imports and exports approximate estimates with the configured global currency', function (): void {
    $settings = app(GeneralSettings::class);
    $settings->currency = 'CHF';
    $settings->save();
    $payload = json_decode(
        (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $payload['schema_version'] = 2;
    $payload['templates'] = [$payload['templates'][0]];
    $payload['templates'][0]['solutions'][0]['estimate_type'] = EstimateType::Approximate->value;
    $payload['templates'][0]['solutions'][0]['amount_min'] = 180;
    $payload['templates'][0]['solutions'][0]['amount_max'] = null;
    $payload['templates'][0]['solutions'][0]['currency_code'] = 'USD';

    app(ImportFindingTemplates::class)(json_encode($payload, JSON_THROW_ON_ERROR), 'replace');

    $solution = FindingTemplateSolution::query()
        ->where('external_id', $payload['templates'][0]['solutions'][0]['external_id'])
        ->sole();
    $export = json_decode(app(ExportFindingTemplates::class)(), true, flags: JSON_THROW_ON_ERROR);

    expect($solution->estimate_type)->toBe(EstimateType::Approximate)
        ->and($solution->currency_code)->toBe('CHF')
        ->and($export['templates'][0]['solutions'][0]['estimate_type'])->toBe(EstimateType::Approximate->value)
        ->and($export['templates'][0]['solutions'][0]['currency_code'])->toBe('CHF');
});

it('previews conflicts and skips existing templates without changing them', function (): void {
    $json = (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json'));
    app(ImportFindingTemplates::class)($json, 'replace');
    $originalTitle = FindingTemplate::query()->where('external_id', 'fixture.exact.monthly')->value('title');

    $preview = app(ImportFindingTemplates::class)->preview($json);
    $result = app(ImportFindingTemplates::class)($json, 'skip');

    expect(array_unique(array_column($preview, 'status')))->toBe(['unchanged'])
        ->and($result)->toBe(['created' => 0, 'replaced' => 0, 'skipped' => 5])
        ->and(FindingTemplate::query()->where('external_id', 'fixture.exact.monthly')->value('title'))->toBe($originalTitle);
});

it('rejects invalid and duplicate input with zero database changes', function (string $fixture): void {
    expect(fn () => app(ImportFindingTemplates::class)(
        (string) file_get_contents(base_path("fixtures/imports/{$fixture}")),
        'replace',
    ))->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0);
})->with([
    'invalid range' => 'invalid-estimate-range.json',
    'invalid recommendation' => 'invalid-multiple-recommended.json',
    'duplicate template identifier' => 'invalid-duplicate-external-id.json',
    'invalid custom billing' => 'invalid-custom-billing.json',
    'invalid non-monetary amount' => 'invalid-nonmonetary-amount.json',
]);

it('rejects the removed tags property under schema version one', function (): void {
    $payload = json_decode(
        (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $payload['templates'][0]['tags'] = ['legacy'];

    expect(fn () => app(ImportFindingTemplates::class)(
        json_encode($payload, JSON_THROW_ON_ERROR),
        'replace',
    ))->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0);
});

it('rejects the removed tags property under canonical schema version two', function (): void {
    $payload = json_decode(
        (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $payload['schema_version'] = 2;
    $payload['templates'][0]['tags'] = ['legacy'];

    expect(fn () => app(ImportFindingTemplates::class)(
        json_encode($payload, JSON_THROW_ON_ERROR),
        'replace',
    ))->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0);
});

it('rejects a fourth imported solution atomically', function (): void {
    $payload = json_decode(
        (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $extra = $payload['templates'][2]['solutions'][1];
    $extra['external_id'] = 'fourth-solution';
    $extra['title'] = 'Quarta soluzione importata';
    $payload['templates'][2]['solutions'][] = $extra;

    expect(fn () => app(ImportFindingTemplates::class)(
        json_encode($payload, JSON_THROW_ON_ERROR),
        'replace',
    ))->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0)
        ->and(FindingTemplateSolution::query()->count())->toBe(0);
});

it('applies the three-solution editorial budget after JSON schema validation', function (): void {
    $payload = json_decode(
        (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );
    $payload['templates'] = [$payload['templates'][0]];
    $payload['templates'][0]['problem'] = str_repeat('x', 851);
    $source = $payload['templates'][0]['solutions'][0];
    $source['recommended'] = false;
    $source['external_id'] = 'second-editorial-option';
    $payload['templates'][0]['solutions'][] = $source;
    $source['external_id'] = 'third-editorial-option';
    $payload['templates'][0]['solutions'][] = $source;

    expect(fn () => app(ImportFindingTemplates::class)(
        json_encode($payload, JSON_THROW_ON_ERROR),
        'replace',
    ))->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0)
        ->and(FindingTemplateSolution::query()->count())->toBe(0);
});
