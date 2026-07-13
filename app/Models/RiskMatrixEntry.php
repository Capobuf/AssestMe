<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property int $id @property int $risk_profile_id @property int $consequence_level_id @property int $likelihood_level_id @property int $priority_level_id */
class RiskMatrixEntry extends Model
{
    /** @var list<string> */
    protected $fillable = ['risk_profile_id', 'consequence_level_id', 'likelihood_level_id', 'priority_level_id'];

    /** @return BelongsTo<RiskProfile, $this> */
    public function riskProfile(): BelongsTo
    {
        return $this->belongsTo(RiskProfile::class);
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
}
