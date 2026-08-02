<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Models;

use Database\Factories\EvaluationItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 면접 평가 체크리스트 항목 (업무흐름 §2).
 *
 * 관리자가 콘솔에서 항목·배점을 조정한다. 합격/보류 판정은 총점이 아니라
 * **만점 대비 비율**로 하므로(EvaluateCandidateAction) 항목을 늘리거나 배점을
 * 바꿔도 기준이 어긋나지 않는다.
 */
class EvaluationItem extends Model
{
    /** @use HasFactory<EvaluationItemFactory> */
    use HasFactory;

    protected $fillable = ['key', 'label', 'hint', 'max_score', 'sort_order', 'active'];

    protected function casts(): array
    {
        return [
            'max_score' => 'integer',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * 평가 시트에 쓰는 항목 (사용 중인 것만, 표시 순서대로).
     *
     * @param  Builder<EvaluationItem>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * 현재 평가 시트.
     *
     * @return Collection<int, self>
     */
    public static function sheet(): Collection
    {
        return self::query()->active()->get();
    }

    /** 시트 만점 합계. 항목이 하나도 없으면 0. */
    public static function totalMaxScore(): int
    {
        return (int) self::query()->active()->sum('max_score');
    }
}
