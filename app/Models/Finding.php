<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FindingStatus;
use App\Enums\ScopeType;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string|null $title
 * @property int $assessment_id
 * @property int|null $source_template_id
 * @property int|null $category_id
 * @property string|null $problem
 * @property string|null $entrepreneur_notes
 * @property string|null $technical_notes
 * @property ScopeType $scope_type
 * @property string|null $scope_description
 * @property int|null $consequence_level_id
 * @property int|null $likelihood_level_id
 * @property int|null $priority_level_id
 * @property bool $priority_is_overridden
 * @property string|null $priority_rationale
 * @property int|null $recommended_solution_id
 * @property int|null $implemented_solution_id
 * @property FindingStatus $status
 * @property string|null $resolution_notes
 * @property Carbon|null $resolved_at
 * @property bool $include_in_report
 * @property int $sort_order
 * @property-read Assessment $assessment
 * @property-read FindingTemplate|null $sourceTemplate
 * @property-read Category|null $category
 * @property-read ConsequenceLevel|null $consequenceLevel
 * @property-read LikelihoodLevel|null $likelihoodLevel
 * @property-read PriorityLevel|null $priorityLevel
 * @property-read FindingSolution|null $recommendedSolution
 * @property-read FindingSolution|null $implementedSolution
 * @property-read Collection<int, FindingSolution> $solutions
 * @property-read Collection<int, Evidence> $evidences
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, Site> $sites
 * @property-read Collection<int, Asset> $assets
 */
class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'assessment_id',
        'source_template_id',
        'title',
        'category_id',
        'problem',
        'entrepreneur_notes',
        'technical_notes',
        'scope_type',
        'scope_description',
        'consequence_level_id',
        'likelihood_level_id',
        'priority_level_id',
        'priority_is_overridden',
        'priority_rationale',
        'recommended_solution_id',
        'implemented_solution_id',
        'status',
        'resolution_notes',
        'resolved_at',
        'include_in_report',
        'sort_order',
    ];

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return BelongsTo<FindingTemplate, $this> */
    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(FindingTemplate::class, 'source_template_id');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<ConsequenceLevel, $this> */
    public function consequenceLevel(): BelongsTo
    {
        return $this->belongsTo(ConsequenceLevel::class);
    }

    /** @return BelongsTo<LikelihoodLevel, $this> */
    public function likelihoodLevel(): BelongsTo
    {
        return $this->belongsTo(LikelihoodLevel::class);
    }

    /** @return BelongsTo<PriorityLevel, $this> */
    public function priorityLevel(): BelongsTo
    {
        return $this->belongsTo(PriorityLevel::class);
    }

    /** @return HasMany<FindingSolution, $this> */
    public function solutions(): HasMany
    {
        return $this->hasMany(FindingSolution::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<FindingSolution, $this> */
    public function recommendedSolution(): BelongsTo
    {
        return $this->belongsTo(FindingSolution::class, 'recommended_solution_id');
    }

    /** @return BelongsTo<FindingSolution, $this> */
    public function implementedSolution(): BelongsTo
    {
        return $this->belongsTo(FindingSolution::class, 'implemented_solution_id');
    }

    /** @return HasMany<Evidence, $this> */
    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class)->orderBy('sort_order');
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'finding_tag');
    }

    /** @return BelongsToMany<Site, $this> */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'finding_site');
    }

    /** @return BelongsToMany<Asset, $this> */
    public function assets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'finding_asset');
    }

    public function recommendedSolutionDescription(): string
    {
        return $this->recommended_solution_id === null ? '—' : $this->recommendedSolution->description;
    }

    public function priorityLabel(): string
    {
        return $this->priority_level_id === null ? '—' : $this->priorityLevel->label;
    }

    public function recommendedEffortLabel(): string
    {
        if ($this->recommended_solution_id === null) {
            return '—';
        }

        $solution = $this->recommendedSolution;

        return $solution->effort_level_id === null ? '—' : $solution->effortLevel->label;
    }

    public function recommendedEstimateLabel(): string
    {
        return $this->recommended_solution_id === null ? '—' : $this->recommendedSolution->formattedEstimate();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scope_type' => ScopeType::class,
            'priority_is_overridden' => 'boolean',
            'status' => FindingStatus::class,
            'resolved_at' => 'datetime',
            'include_in_report' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
