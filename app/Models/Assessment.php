<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssessmentStatus;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $title
 * @property Carbon $assessment_date
 * @property AssessmentStatus $status
 * @property int $lock_version
 */
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'assessment_date',
        'status',
        'lock_version',
        'completed_at',
    ];

    /** @return HasMany<Finding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class)->orderBy('sort_order');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'status' => AssessmentStatus::class,
            'lock_version' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
