<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id @property int $risk_profile_id @property string $code @property string $label @property string $color @property int $sort_order @property bool $is_enabled */
class PriorityLevel extends Model
{
    /** @var list<string> */
    protected $fillable = ['risk_profile_id', 'code', 'label', 'description', 'color', 'sort_order', 'is_enabled'];

    /** @return BelongsTo<RiskProfile, $this> */
    public function riskProfile(): BelongsTo
    {
        return $this->belongsTo(RiskProfile::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_enabled' => 'boolean'];
    }
}
