<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use Database\Factories\LifeChecklistItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 한국 생활 체크리스트 항목 (입국 후 1주일 이내 확인사항).
 *
 * 문구는 한국어로 하나만 두고 근로자 언어로는 실시간 번역해 내보낸다
 * (안내 자료와 같은 방식). 체크 기록은 code 가 아니라 id 로 붙는다.
 */
class LifeChecklistItem extends Model
{
    /** @use HasFactory<LifeChecklistItemFactory> */
    use HasFactory;

    protected $fillable = ['code', 'label', 'hint', 'sort_order', 'active'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<LifeChecklistCheck, $this> */
    public function checks(): HasMany
    {
        return $this->hasMany(LifeChecklistCheck::class);
    }

    /**
     * 근로자에게 보여줄 순서대로, 사용 중인 항목만.
     *
     * @param  Builder<LifeChecklistItem>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('sort_order')->orderBy('id');
    }
}
