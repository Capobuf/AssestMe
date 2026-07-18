<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScopeType;
use Database\Factories\FindingTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use LogicException;

/**
 * @property int $id
 * @property int $category_id
 * @property int|null $default_consequence_level_id
 * @property int|null $default_likelihood_level_id
 * @property int|null $default_priority_level_id
 * @property string $external_id
 * @property string $title
 * @property string $problem
 * @property string|null $entrepreneur_notes
 * @property string|null $technical_notes
 * @property ScopeType $default_scope_type
 * @property string|null $default_scope_description
 * @property string|null $priority_rationale
 * @property bool $is_enabled
 * @property-read Category $category
 * @property-read Collection<int, Tag> $tags
 * @property-read Collection<int, FindingTemplateSolution> $solutions
 * @property-read ConsequenceLevel|null $defaultConsequenceLevel
 * @property-read LikelihoodLevel|null $defaultLikelihoodLevel
 * @property-read PriorityLevel|null $defaultPriorityLevel
 */
class FindingTemplate extends Model
{
    /** @use HasFactory<FindingTemplateFactory> */
    use HasFactory;

    use SoftDeletes;

    protected static function booted(): void
    {
        self::updating(static function (self $template): void {
            if ($template->isDirty('external_id')) {
                throw new LogicException('Finding template external identifiers are immutable.');
            }
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'external_id', 'title', 'category_id', 'problem', 'entrepreneur_notes', 'technical_notes',
        'default_scope_type', 'default_scope_description', 'default_consequence_level_id',
        'default_likelihood_level_id', 'default_priority_level_id', 'priority_rationale', 'is_enabled',
    ];

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'finding_template_tag');
    }

    /** @return HasMany<FindingTemplateSolution, $this> */
    public function solutions(): HasMany
    {
        return $this->hasMany(FindingTemplateSolution::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<ConsequenceLevel, $this> */
    public function defaultConsequenceLevel(): BelongsTo
    {
        return $this->belongsTo(ConsequenceLevel::class, 'default_consequence_level_id');
    }

    /** @return BelongsTo<LikelihoodLevel, $this> */
    public function defaultLikelihoodLevel(): BelongsTo
    {
        return $this->belongsTo(LikelihoodLevel::class, 'default_likelihood_level_id');
    }

    /** @return BelongsTo<PriorityLevel, $this> */
    public function defaultPriorityLevel(): BelongsTo
    {
        return $this->belongsTo(PriorityLevel::class, 'default_priority_level_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['default_scope_type' => ScopeType::class, 'is_enabled' => 'boolean'];
    }
}
