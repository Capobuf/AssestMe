<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceSaveRequest extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $primaryKey = 'request_id';

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = [
        'request_id',
        'assessment_id',
        'expected_version',
        'applied_version',
        'payload_hash',
        'response',
        'created_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expected_version' => 'integer',
            'applied_version' => 'integer',
            'response' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
