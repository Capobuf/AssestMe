<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $assessment_id
 * @property string|null $last_content_hash
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property Carbon|null $last_error_at
 * @property-read Assessment $assessment
 */
final class AssessmentGoogleDriveSync extends Model
{
    protected $primaryKey = 'assessment_id';

    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'assessment_id',
        'last_content_hash',
        'last_synced_at',
        'last_error',
        'last_error_at',
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
            'assessment_id' => 'integer',
            'last_synced_at' => 'datetime',
            'last_error_at' => 'datetime',
        ];
    }
}
