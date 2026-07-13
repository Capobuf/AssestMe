<?php

declare(strict_types=1);

use App\Actions\Templates\ExportFindingTemplates;
use App\Actions\Templates\ImportFindingTemplates;
use App\Models\FindingTemplate;
use App\Models\FindingTemplateSolution;
use Database\Seeders\MilestoneOneSeeder;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(MilestoneOneSeeder::class);
});

it('imports every valid template atomically and exports a data-equivalent document', function (): void {
    $json = (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json'));
    $result = app(ImportFindingTemplates::class)->handle($json, 'replace');

    expect($result)->toBe(['created' => 5, 'replaced' => 0, 'skipped' => 0])
        ->and(FindingTemplate::query()->count())->toBe(5)
        ->and(FindingTemplateSolution::query()->count())->toBe(11)
        ->and(FindingTemplate::query()->whereHas('solutions', fn ($query) => $query->where('is_recommended', true))->count())->toBe(5);

    $export = app(ExportFindingTemplates::class)->handle();
    $roundTrip = app(ImportFindingTemplates::class)->handle($export, 'replace');

    expect($roundTrip)->toBe(['created' => 0, 'replaced' => 5, 'skipped' => 0])
        ->and(json_decode(app(ExportFindingTemplates::class)->handle(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe(json_decode($export, true, flags: JSON_THROW_ON_ERROR));
});

it('previews conflicts and skips existing templates without changing them', function (): void {
    $json = (string) file_get_contents(base_path('fixtures/imports/valid-all-branches.json'));
    app(ImportFindingTemplates::class)->handle($json, 'replace');
    $originalTitle = FindingTemplate::query()->where('external_id', 'fixture.exact.monthly')->value('title');

    $preview = app(ImportFindingTemplates::class)->preview($json);
    $result = app(ImportFindingTemplates::class)->handle($json, 'skip');

    expect(array_unique(array_column($preview, 'status')))->toBe(['unchanged'])
        ->and($result)->toBe(['created' => 0, 'replaced' => 0, 'skipped' => 5])
        ->and(FindingTemplate::query()->where('external_id', 'fixture.exact.monthly')->value('title'))->toBe($originalTitle);
});

it('rejects invalid and duplicate input with zero database changes', function (string $fixture): void {
    expect(fn () => app(ImportFindingTemplates::class)->handle(
        (string) file_get_contents(base_path("fixtures/imports/{$fixture}")),
        'replace',
    ))->toThrow(ValidationException::class)
        ->and(FindingTemplate::query()->count())->toBe(0);
})->with([
    'invalid range' => 'invalid-estimate-range.json',
    'invalid recommendation' => 'invalid-multiple-recommended.json',
    'duplicate template identifier' => 'invalid-duplicate-external-id.json',
    'invalid custom billing' => 'invalid-custom-billing.json',
]);
