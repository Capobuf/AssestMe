<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvidenceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $finding_id
 * @property EvidenceType $type
 * @property string $title
 * @property string|null $file_path
 * @property string|null $url
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $size_bytes
 * @property string|null $sha256
 * @property bool $include_in_report
 * @property int $sort_order
 * @property-read Finding $finding
 */
final class Evidence extends Model
{
    use SoftDeletes;

    protected $table = 'evidences';

    /** @var list<string> */
    protected $fillable = [
        'finding_id',
        'type',
        'title',
        'file_path',
        'url',
        'original_filename',
        'caption',
        'internal_notes',
        'include_in_report',
        'mime_type',
        'size_bytes',
        'sha256',
        'sort_order',
    ];

    /** @return BelongsTo<Finding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => EvidenceType::class,
            'include_in_report' => 'boolean',
            'size_bytes' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
