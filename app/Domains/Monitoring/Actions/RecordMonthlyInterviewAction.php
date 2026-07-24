<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Actions;

use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;

/**
 * 월별 인터뷰 기록 (CLAUDE.md §4, 업무흐름 §7).
 *
 * 6개 항목 응답으로 이탈 리스크 스코어(부정 신호 수)와 등급을 산출해 함께 저장한다.
 * 리스크는 인터뷰 응답 등 행동 신호 기반이며 위치 추적을 쓰지 않는다.
 */
class RecordMonthlyInterviewAction
{
    /**
     * @param  array<string, mixed>  $items  6개 항목 boolean (미지정은 true=양호로 간주)
     */
    public function execute(
        Worker $worker,
        User $inspector,
        string $interviewedOn,
        array $items,
        ?string $memo = null,
        ?int $checkinId = null,
    ): MonthlyInterview {
        $answers = [];
        $negatives = 0;

        foreach (MonthlyInterview::ITEMS as $item) {
            $ok = (bool) ($items[$item] ?? true);
            $answers[$item] = $ok;
            if (! $ok) {
                $negatives++;
            }
        }

        return MonthlyInterview::create([
            'worker_id' => $worker->id,
            'inspector_user_id' => $inspector->id,
            'inspection_checkin_id' => $checkinId,
            'interviewed_on' => $interviewedOn,
            ...$answers,
            'risk_score' => $negatives,
            'risk_level' => RiskLevel::fromNegativeCount($negatives),
            'memo' => $memo,
        ]);
    }
}
