<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RiskProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** @property int $id @property string $code @property string $label @property bool $is_default @property bool $is_enabled */
class RiskProfile extends Model
{
    /** @use HasFactory<RiskProfileFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['code', 'label', 'description', 'is_default', 'is_enabled'];

    /** @return HasMany<ConsequenceLevel, $this> */
    public function consequenceLevels(): HasMany
    {
        return $this->hasMany(ConsequenceLevel::class)->orderBy('sort_order');
    }

    /** @return HasMany<LikelihoodLevel, $this> */
    public function likelihoodLevels(): HasMany
    {
        return $this->hasMany(LikelihoodLevel::class)->orderBy('sort_order');
    }

    /** @return HasMany<PriorityLevel, $this> */
    public function priorityLevels(): HasMany
    {
        return $this->hasMany(PriorityLevel::class)->orderBy('sort_order');
    }

    /** @return HasMany<RiskMatrixEntry, $this> */
    public function matrixEntries(): HasMany
    {
        return $this->hasMany(RiskMatrixEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_enabled' => 'boolean'];
    }
}
