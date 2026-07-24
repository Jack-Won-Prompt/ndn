<?php

declare(strict_types=1);

namespace App\Domains\Demand\Models;

use App\Models\User;
use Database\Factories\FarmFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 농가.
 */
class Farm extends Model
{
    /** @use HasFactory<FarmFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'owner_user_id',
        'city_id',
        'name',
        'address',
        'contact_phone',
        'main_crop',
    ];

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** @return HasMany<DemandRequest, $this> */
    public function demandRequests(): HasMany
    {
        return $this->hasMany(DemandRequest::class);
    }
}
