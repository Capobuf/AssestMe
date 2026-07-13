<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $client_id
 * @property int|null $site_id
 * @property int $asset_type_id
 * @property string|null $name
 * @property string|null $mac_address
 */
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'client_id',
        'site_id',
        'asset_type_id',
        'name',
        'manufacturer',
        'model',
        'hostname',
        'ip_address',
        'mac_address',
        'serial_number',
        'description',
        'notes',
    ];

    /** @return BelongsTo<Client, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<AssetType, $this> */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }
}
