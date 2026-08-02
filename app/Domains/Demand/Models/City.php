<?php

declare(strict_types=1);

namespace App\Domains\Demand\Models;

use App\Domains\Recruitment\Models\Worker;
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

    protected $fillable = ['name', 'region', 'quota', 'recruiting'];

    protected function casts(): array
    {
        return [
            'quota' => 'integer',
            'recruiting' => 'boolean',
        ];
    }

    /** 화면·앱에 쓰는 표시명 — "충청남도 당진시". */
    public function label(): string
    {
        return trim(($this->region ?? '').' '.$this->name);
    }

    /**
     * 이 지역으로 새 가입을 받을 수 있는가.
     *
     * 모집을 닫았거나(recruiting=false), 정원이 정해져 있고 이미 그만큼 지원자가
     * 있으면 받지 않는다. 정원이 null 이면 인원 제한이 없다.
     */
    public function isOpenForSignup(): bool
    {
        if (! $this->recruiting) {
            return false;
        }

        return $this->quota === null || $this->workers()->count() < $this->quota;
    }

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

    /**
     * 이 지자체에 지원한 근로자 (가입 시 선택). 실제 배치와는 다를 수 있다.
     *
     * @return HasMany<Worker, $this>
     */
    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }
}
