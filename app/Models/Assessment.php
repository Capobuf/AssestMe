<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Enums\ScopeType;
use Database\Factories\AssessmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property string $title
 * @property int $client_id
 * @property string|null $report_title_override
 * @property Carbon $assessment_date
 * @property ScopeType $scope_type
 * @property string|null $scope_description
 * @property string|null $introduction
 * @property string|null $executive_summary
 * @property string|null $methodology_notes
 * @property string $locale
 * @property AssessmentStatus $status
 * @property int $lock_version
 * @property Carbon|null $completed_at
 * @property-read Client $client
 * @property-read Collection<int, Site> $sites
 * @property-read Collection<int, GeneratedReport> $generatedReports
 * @property-read AssessmentGoogleDriveSync|null $googleDriveSync
 */
class Assessment extends Model
{
    /** @use HasFactory<AssessmentFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'title',
        'report_title_override',
        'assessment_date',
        'scope_type',
        'scope_description',
        'introduction',
        'executive_summary',
        'methodology_notes',
        'locale',
        'status',
        'lock_version',
        'completed_at',
    ];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsToMany<Site, $this> */
    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'assessment_site');
    }

    /** @return HasMany<Finding, $this> */
    public function findings(): HasMany
    {
        return $this->hasMany(Finding::class)->orderBy('sort_order');
    }

    /** @return HasMany<GeneratedReport, $this> */
    public function generatedReports(): HasMany
    {
        return $this->hasMany(GeneratedReport::class);
    }

    /** @return HasOne<AssessmentGoogleDriveSync, $this> */
    public function googleDriveSync(): HasOne
    {
        return $this->hasOne(AssessmentGoogleDriveSync::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
            'scope_type' => ScopeType::class,
            'status' => AssessmentStatus::class,
            'lock_version' => 'integer',
            'completed_at' => 'datetime',
        ];
    }
}
