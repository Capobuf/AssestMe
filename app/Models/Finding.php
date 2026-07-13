<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EffortLevel;
use App\Enums\EstimateType;
use App\Enums\FindingPriority;
use App\Enums\FindingStatus;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string|null $title
 * @property string|null $problem
 * @property string|null $entrepreneur_notes
 * @property string|null $recommended_solution_summary
 * @property FindingPriority|null $priority
 * @property EffortLevel|null $effort
 * @property EstimateType|null $estimate_type
 * @property string|null $estimate_notes
 * @property FindingStatus $status
 * @property bool $include_in_report
 * @property int $sort_order
 */
class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * `recommended_solution_summary` is a Milestone 0 projection only. The
     * definitive model reads and writes the related recommended solution.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'problem',
        'entrepreneur_notes',
        'recommended_solution_summary',
        'priority',
        'effort',
        'estimate_type',
        'estimate_notes',
        'status',
        'include_in_report',
        'sort_order',
    ];

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'priority' => FindingPriority::class,
            'effort' => EffortLevel::class,
            'estimate_type' => EstimateType::class,
            'status' => FindingStatus::class,
            'include_in_report' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
