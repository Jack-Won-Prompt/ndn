<?php

declare(strict_types=1);

namespace App\Domains\Demand\Models;

use App\Domains\Demand\Enums\DemandStatus;
use App\Shared\Enums\Gender;
use Database\Factories\DemandRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 농가 수요 신청 (CLAUDE.md §5).
 *
 * @property DemandStatus $status
 * @property Gender $gender
 */
class DemandRequest extends Model
{
    /** @use HasFactory<DemandRequestFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'farm_id',
        'city_id',
        'nationality',
        'headcount',
        'age_min',
        'age_max',
        'gender',
        'allow_siblings',
        'crop',
        'period_start',
        'period_end',
        'note',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DemandStatus::class,
            'gender' => Gender::class,
            'allow_siblings' => 'boolean',
            'headcount' => 'integer',
            'age_min' => 'integer',
            'age_max' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /** @return BelongsTo<City, $this> */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /**
     * 특정 상태만 필터.
     *
     * @param  Builder<DemandRequest>  $query
     */
    public function scopeStatus(Builder $query, DemandStatus $status): void
    {
        $query->where('status', $status->value);
    }
}
