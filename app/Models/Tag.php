<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 */
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'slug',
        'color',
    ];
}
