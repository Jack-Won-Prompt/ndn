<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Domains\Monitoring\Enums\WorkReviewSection;
use Database\Factories\WorkReviewItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 근무상태 종합 점검표의 점검 항목 하나.
 *
 * @property WorkReviewSection $section
 */
class WorkReviewItem extends Model
{
    /** @use HasFactory<WorkReviewItemFactory> */
    use HasFactory;

    /** 이탈 판단의 핵심 항목 — 이 항목이 '미흡'이면 다른 점수와 무관하게 고위험이다. */
    public const FLIGHT_RISK = 'flight_risk';

    protected $fillable = ['section', 'code', 'label', 'adverse', 'scored', 'sort_order', 'active'];

    protected function casts(): array
    {
        return [
            'section' => WorkReviewSection::class,
            'adverse' => 'boolean',
            'scored' => 'boolean',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * 점검 화면에 보여줄 순서대로, 사용 중인 항목만.
     *
     * @param  Builder<WorkReviewItem>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('sort_order')->orderBy('id');
    }

    /** 이 응답이 나쁜 신호인가. */
    public function isBad(string $value): bool
    {
        if ($this->section->isRating()) {
            return $value === 'low';
        }

        // 확인·미확인 항목은 방향이 항목마다 다르다 (adverse 주석 참조).
        return $this->adverse ? $value === 'yes' : $value === 'no';
    }

    /** 좋지도 나쁘지도 않은 중간 응답인가 (3단계에만 있다). */
    public function isMiddling(string $value): bool
    {
        return $this->section->isRating() && $value === 'mid';
    }
}
