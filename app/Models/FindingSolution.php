<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BillingFrequency;
use App\Enums\EstimateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $finding_id
 * @property string|null $external_key
 * @property string $title
 * @property string $description
 * @property string|null $comparison_notes
 * @property int|null $effort_level_id
 * @property string|null $effort_notes
 * @property EstimateType $estimate_type
 * @property string|null $amount_min
 * @property string|null $amount_max
 * @property string|null $currency_code
 * @property BillingFrequency $billing_frequency
 * @property string|null $custom_billing_frequency
 * @property string|null $estimate_notes
 * @property int $sort_order
 * @property-read Finding $finding
 * @property-read EffortLevel|null $effortLevel
 */
final class FindingSolution extends Model
{
    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'finding_id',
        'external_key',
        'title',
        'description',
        'comparison_notes',
        'effort_level_id',
        'effort_notes',
        'estimate_type',
        'amount_min',
        'amount_max',
        'currency_code',
        'billing_frequency',
        'custom_billing_frequency',
        'estimate_notes',
        'sort_order',
    ];

    /** @return BelongsTo<Finding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /** @return BelongsTo<EffortLevel, $this> */
    public function effortLevel(): BelongsTo
    {
        return $this->belongsTo(EffortLevel::class);
    }

    public function formattedEstimate(): string
    {
        $estimateLabel = EstimateType::options()[$this->estimate_type->value];
        if ($this->amount_min === null || $this->currency_code === null) {
            return $estimateLabel;
        }

        $minimum = number_format((float) $this->amount_min, 2, ',', '.').' '.$this->currency_code;
        if ($this->estimate_type === EstimateType::Approximate) {
            return $estimateLabel.': '.$minimum;
        }
        if ($this->estimate_type === EstimateType::Range && $this->amount_max !== null) {
            return $minimum.' – '.number_format((float) $this->amount_max, 2, ',', '.').' '.$this->currency_code;
        }

        return $minimum;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estimate_type' => EstimateType::class,
            'billing_frequency' => BillingFrequency::class,
            'amount_min' => 'decimal:2',
            'amount_max' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
