<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EffortLevelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** @property int $id @property string $code @property string $label @property string $color @property int $sort_order @property bool $is_enabled */
class EffortLevel extends Model
{
    /** @use HasFactory<EffortLevelFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['code', 'label', 'description', 'color', 'sort_order', 'is_enabled'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_enabled' => 'boolean'];
    }
}
