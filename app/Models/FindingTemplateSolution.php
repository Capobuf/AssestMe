<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * @property int $id
 * @property int $finding_template_id
 * @property int|null $effort_level_id
 * @property string $external_id
 * @property string $title
 * @property string $description
 * @property string|null $comparison_notes
 * @property string|null $effort_notes
 * @property EstimateType $estimate_type
 * @property string|null $amount_min
 * @property string|null $amount_max
 * @property string|null $currency_code
 * @property BillingFrequency $billing_frequency
 * @property string|null $custom_billing_frequency
 * @property string|null $estimate_notes
 * @property bool $is_recommended
 * @property int $sort_order
 * @property-read EffortLevel|null $effortLevel
 */
class FindingTemplateSolution extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        self::updating(static function (self $solution): void {
            if ($solution->isDirty('external_id')) {
                throw new LogicException('Finding template solution external identifiers are immutable.');
            }
        });
    }

    /** @var list<string> */
    protected $fillable = [
        'finding_template_id', 'external_id', 'title', 'description', 'comparison_notes', 'effort_level_id',
        'effort_notes', 'estimate_type', 'amount_min', 'amount_max', 'currency_code', 'billing_frequency',
        'custom_billing_frequency', 'estimate_notes', 'is_recommended', 'sort_order',
    ];

    /** @return BelongsTo<FindingTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(FindingTemplate::class, 'finding_template_id');
    }

    /** @return BelongsTo<EffortLevel, $this> */
    public function effortLevel(): BelongsTo
    {
        return $this->belongsTo(EffortLevel::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estimate_type' => EstimateType::class,
            'billing_frequency' => BillingFrequency::class,
            'amount_min' => 'decimal:2',
            'amount_max' => 'decimal:2',
            'is_recommended' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
