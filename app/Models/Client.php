<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $legal_name
 * @property string|null $trade_name
 * @property string|null $vat_number
 * @property string|null $tax_code
 */
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'legal_name',
        'trade_name',
        'vat_number',
        'tax_code',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'postal_code',
        'province',
        'country',
        'logo_path',
        'internal_notes',
    ];

    /** @return HasMany<Site, $this> */
    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function displayName(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }
}
