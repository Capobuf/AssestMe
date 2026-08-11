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
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postal_code
 * @property string|null $province
 * @property string $country
 * @property string|null $fatture_in_cloud_company_id
 * @property string|null $fatture_in_cloud_client_id
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
        'fatture_in_cloud_company_id',
        'fatture_in_cloud_client_id',
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

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function displayName(): string
    {
        return $this->trade_name ?: $this->legal_name;
    }
}
