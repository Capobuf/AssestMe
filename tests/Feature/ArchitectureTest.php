<?php

declare(strict_types=1);

use App\Actions\Assessments\ArchiveAssessment;
use App\Actions\Assessments\CompleteAssessment;
use App\Actions\Assessments\CopyTemplateToAssessment;
use App\Actions\Assessments\CreateAssessment;
use App\Actions\Assessments\CreateBlankFinding;
use App\Actions\Assessments\DuplicateFinding;
use App\Actions\Assessments\OverrideFindingPriority;
use App\Actions\Assessments\ReopenAssessment;
use App\Actions\Assessments\ReorderFindings;
use App\Actions\Assessments\SaveAssessmentPatch;
use App\Actions\Assessments\SaveAssessmentWorkspace;
use App\Actions\Assessments\SetImplementedSolution;
use App\Actions\Assessments\SetRecommendedSolution;
use App\Actions\Assessments\TransitionFindingStatus;
use App\Actions\Backups\CreateBackup;
use App\Actions\Backups\RestoreBackup;
use App\Actions\Evidence\DeleteEvidence;
use App\Actions\Evidence\StoreEvidence;
use App\Actions\Reports\BuildAssessmentSnapshot;
use App\Actions\Reports\GenerateAssessmentPdf;
use App\Actions\Reports\GenerateAssessmentWorkbook;
use App\Actions\Risk\CalculateFindingPriority;
use App\Actions\Storage\DeleteEntityAccordingToPolicy;
use App\Actions\Templates\ExportFindingTemplates;
use App\Actions\Templates\ImportFindingTemplates;
use App\Models\Assessment;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\Client;
use App\Models\EffortLevel;
use App\Models\Evidence;
use App\Models\FindingTemplate;
use App\Models\GeneratedReport;
use App\Models\RiskProfile;
use App\Models\Site;
use App\Models\Tag;
use App\Models\User;
use App\Policies\SingletonAdministratorPolicy;
use Illuminate\Support\Facades\Gate;

it('keeps application PHP strict and free of debug calls or direct environment access', function (): void {
    $files = collect(File::allFiles(app_path()))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php');

    foreach ($files as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        expect($contents)
            ->toContain('declare(strict_types=1);')
            ->not->toMatch('/\b(?:dd|dump|ray)\s*\(/')
            ->not->toMatch('/\benv\s*\(/');
    }
});

it('keeps business actions independent from Filament', function (): void {
    foreach (File::allFiles(app_path('Actions')) as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        expect($contents)->not->toContain('Filament\\');
    }
});

it('keeps every approved business action invokable without handle aliases', function (string $action): void {
    expect(class_exists($action))->toBeTrue();

    $reflection = new ReflectionClass($action);

    expect($reflection->hasMethod('__invoke'))->toBeTrue()
        ->and($reflection->getMethod('__invoke')->isPublic())->toBeTrue()
        ->and($reflection->hasMethod('handle'))->toBeFalse();
})->with([
    CreateAssessment::class,
    CompleteAssessment::class,
    ReopenAssessment::class,
    ArchiveAssessment::class,
    CreateBlankFinding::class,
    CopyTemplateToAssessment::class,
    DuplicateFinding::class,
    ReorderFindings::class,
    SaveAssessmentPatch::class,
    SaveAssessmentWorkspace::class,
    CalculateFindingPriority::class,
    OverrideFindingPriority::class,
    SetRecommendedSolution::class,
    SetImplementedSolution::class,
    TransitionFindingStatus::class,
    StoreEvidence::class,
    DeleteEvidence::class,
    DeleteEntityAccordingToPolicy::class,
    ImportFindingTemplates::class,
    ExportFindingTemplates::class,
    BuildAssessmentSnapshot::class,
    GenerateAssessmentPdf::class,
    GenerateAssessmentWorkbook::class,
    CreateBackup::class,
    RestoreBackup::class,
]);

it('does not contain a PHPStan baseline', function (): void {
    expect(base_path('phpstan-baseline.neon'))->not->toBeFile();
});

it('does not bypass the staged deletion policy in Filament resources', function (): void {
    foreach (File::allFiles(app_path('Filament/Resources')) as $file) {
        $contents = (string) file_get_contents($file->getPathname());

        expect($contents)->not->toMatch('/\b(?:DeleteAction|DeleteBulkAction|ForceDeleteAction|ForceDeleteBulkAction)::make\(/');
    }
});

it('maps every directly exposed model to the singleton administrator policy', function (string $model): void {
    expect(Gate::getPolicyFor($model))->toBeInstanceOf(SingletonAdministratorPolicy::class);
})->with([
    Assessment::class,
    Asset::class,
    AssetType::class,
    Category::class,
    Client::class,
    EffortLevel::class,
    Evidence::class,
    FindingTemplate::class,
    GeneratedReport::class,
    RiskProfile::class,
    Site::class,
    Tag::class,
]);

it('allows only the persisted singleton administrator and denies deletion bypass abilities', function (): void {
    $administrator = User::factory()->create();
    $client = Client::factory()->create();
    $unpersistedUser = User::factory()->make(['singleton_key' => 1]);

    expect(Gate::forUser($administrator)->allows('viewAny', Client::class))->toBeTrue()
        ->and(Gate::forUser($administrator)->allows('view', $client))->toBeTrue()
        ->and(Gate::forUser($administrator)->allows('create', Client::class))->toBeTrue()
        ->and(Gate::forUser($administrator)->allows('update', $client))->toBeTrue()
        ->and(Gate::forUser($administrator)->allows('delete', $client))->toBeTrue()
        ->and(Gate::forUser($administrator)->allows('restore', $client))->toBeTrue()
        ->and(Gate::forUser($administrator)->allows('deleteAny', Client::class))->toBeFalse()
        ->and(Gate::forUser($administrator)->allows('forceDelete', $client))->toBeFalse()
        ->and(Gate::forUser($administrator)->allows('forceDeleteAny', Client::class))->toBeFalse()
        ->and(Gate::forUser($unpersistedUser)->allows('viewAny', Client::class))->toBeFalse();
});
