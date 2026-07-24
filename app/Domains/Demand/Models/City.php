<?php

declare(strict_types=1);

namespace App\Domains\Demand\Models;

use Database\Factories\CityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 지자체(시청).
 */
class City extends Model
{
    /** @use HasFactory<CityFactory> */
    use HasFactory;

    protected $fillable = ['name', 'region'];

    /** @return HasMany<Farm, $this> */
    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class);
    }

    /** @return HasMany<DemandRequest, $this> */
    public function demandRequests(): HasMany
    {
        return $this->hasMany(DemandRequest::class);
    }
}
