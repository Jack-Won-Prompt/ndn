<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use Database\Factories\WorkerGuideFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 근로자 안내 자료 한 편 (앱 정보 화면 하나에 대응).
 *
 * 한국어 원문만 들고 있고 근로자 언어로는 실시간 번역해 내보낸다
 * (WorkerGuidePresenter). 동의를 받는 문서는 RequiredDocument 로 따로 있다.
 */
class WorkerGuide extends Model
{
    /** @use HasFactory<WorkerGuideFactory> */
    use HasFactory;

    protected $fillable = ['key', 'title', 'lead', 'icon', 'position', 'active'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<WorkerGuideSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(WorkerGuideSection::class)->orderBy('position');
    }

    /**
     * 근로자에게 보여줄 순서대로, 사용 중인 자료만.
     *
     * @param  Builder<WorkerGuide>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('position')->orderBy('id');
    }
}
