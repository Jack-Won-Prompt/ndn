<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Actions;

use App\Domains\Monitoring\Enums\InterviewSource;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use RuntimeException;

/**
 * 근로 생활 자가 평가 제출 (CLAUDE.md §4, 업무흐름 §7).
 *
 * 근로자가 앱에서 6개 항목에 직접 응답한다. 점검자 방문 기록과 동일한 스키마에
 * source=self 로 저장해, 리스크 산출·보고서가 두 경로를 함께 다룰 수 있게 한다.
 *
 * 리스크는 응답(행동 신호)만으로 산출하며 위치 추적을 쓰지 않는다(§7-2).
 */
class SubmitSelfAssessmentAction
{
    /**
     * 같은 달에 이미 자가 평가를 제출했으면 그 행을 갱신한다(월 1건).
     *
     * @param  array<string, mixed>  $items  6개 항목 boolean (미지정은 true=양호로 간주)
     *
     * @throws RuntimeException
     */
    public function execute(Worker $worker, array $items, ?string $memo = null): MonthlyInterview
    {
        $answers = [];
        $negatives = 0;

        foreach (MonthlyInterview::ITEMS as $item) {
            $ok = (bool) ($items[$item] ?? true);
            $answers[$item] = $ok;
            if (! $ok) {
                $negatives++;
            }
        }

        $today = now()->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $existing = MonthlyInterview::query()
            ->where('worker_id', $worker->id)
            ->where('source', InterviewSource::Self->value)
            ->whereBetween('interviewed_on', [$monthStart, $monthEnd])
            ->latest('id')
            ->first();

        $attributes = [
            'worker_id' => $worker->id,
            'inspector_user_id' => null,   // 자가 평가에는 점검자가 없다
            'inspection_checkin_id' => null,
            'interviewed_on' => $today,
            'source' => InterviewSource::Self,
            ...$answers,
            'risk_score' => $negatives,
            'risk_level' => RiskLevel::fromNegativeCount($negatives),
            'memo' => $memo,
        ];

        if ($existing !== null) {
            $existing->fill($attributes)->save();

            return $existing->refresh();
        }

        return MonthlyInterview::create($attributes);
    }
}
