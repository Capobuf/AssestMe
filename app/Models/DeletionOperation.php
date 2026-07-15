<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeletionOperationStatus;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $uuid
 * @property string $entity_type
 * @property int $entity_id
 * @property DeletionOperationStatus $status
 * @property string $trash_path
 * @property list<array{source: string, trash: string, size: int, sha256: string}> $manifest
 * @property string|null $error_text
 */
final class DeletionOperation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'entity_type',
        'entity_id',
        'status',
        'trash_path',
        'manifest',
        'error_text',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'entity_id' => 'integer',
            'status' => DeletionOperationStatus::class,
            'manifest' => 'array',
        ];
    }
}
