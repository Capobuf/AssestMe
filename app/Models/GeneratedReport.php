<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GeneratedReportFormat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $assessment_id
 * @property GeneratedReportFormat $format
 * @property int $version
 * @property string $file_path
 * @property string $file_name
 * @property int $file_size_bytes
 * @property string $file_sha256
 * @property string $payload_sha256
 * @property array<string, mixed> $payload_snapshot
 * @property array<string, mixed> $settings_snapshot
 * @property Carbon $generated_at
 * @property-read Assessment $assessment
 */
final class GeneratedReport extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'assessment_id',
        'format',
        'version',
        'file_path',
        'file_name',
        'file_size_bytes',
        'file_sha256',
        'payload_sha256',
        'payload_snapshot',
        'settings_snapshot',
        'generated_at',
    ];

    /** @return BelongsTo<Assessment, $this> */
    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    protected static function booted(): void
    {
        self::saving(static function (self $report): void {
            if ($report->exists) {
                throw new LogicException('Generated reports are immutable and cannot be saved after creation.');
            }
        });

        self::deleting(static function (): never {
            throw new LogicException('Generated reports are immutable and cannot be deleted through normal model operations.');
        });
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'format' => GeneratedReportFormat::class,
            'version' => 'integer',
            'file_size_bytes' => 'integer',
            'payload_snapshot' => 'array',
            'settings_snapshot' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
