<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Actions;

use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Enums\WorkReviewResult;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Monitoring\Models\WorkReviewItem;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 근무상태 종합 점검표 기록 (CLAUDE.md §4, 업무흐름 §7).
 *
 * 응답에서 이탈 리스크를 산출해 함께 저장한다. 리스크는 점검 응답(행동 신호)만
 * 근거로 하며 위치 추적을 쓰지 않는다(§7-2).
 */
class RecordWorkReviewAction
{
    /** 나쁜 응답에 매기는 점수. 보통은 그 절반. */
    private const BAD = 2;

    private const MIDDLING = 1;

    /**
     * @param  array<string, mixed>  $data  점검 개요·임금·종합의견·조치사항
     * @param  array<int|string, string|array{value: string, note?: string|null}>  $answers  항목 id => 응답
     *
     * @throws RuntimeException 배정된 농가를 알 수 없을 때
     */
    public function execute(Worker $worker, User $inspector, array $data, array $answers): WorkReview
    {
        $farmId = $data['farm_id'] ?? $worker->currentPlacement()?->farm_id;
        if ($farmId === null) {
            throw new RuntimeException('점검할 농가를 알 수 없습니다. 배정을 먼저 확정하세요.');
        }

        $items = WorkReviewItem::query()->active()->get()->keyBy('id');

        return DB::transaction(function () use ($worker, $inspector, $data, $answers, $items, $farmId) {
            $review = WorkReview::create([
                'worker_id' => $worker->id,
                'farm_id' => $farmId,
                'inspector_user_id' => $inspector->id,
                'farm_visit_id' => $data['farm_visit_id'] ?? null,
                'reviewed_at' => $data['reviewed_at'],
                'place' => $data['place'] ?? null,
                'review_type' => $data['review_type'],
                'overtime_done' => $data['overtime_done'] ?? null,
                'overtime_hours' => $data['overtime_hours'] ?? null,
                'overtime_consented' => $data['overtime_consented'] ?? null,
                'avg_monthly_wage' => $data['avg_monthly_wage'] ?? null,
                'last_paid_on' => $data['last_paid_on'] ?? null,
                'wage_unpaid' => (bool) ($data['wage_unpaid'] ?? false),
                'board_provided' => $data['board_provided'] ?? null,
                'contract_followed' => $data['contract_followed'] ?? null,
                'contract_violation' => $data['contract_violation'] ?? null,
                'result' => $data['result'],
                'notable' => $data['notable'] ?? null,
                'improvements' => $data['improvements'] ?? null,
                'farm_requests' => $data['farm_requests'] ?? null,
                'action_due_on' => $data['action_due_on'] ?? null,
                'action_assignee' => $data['action_assignee'] ?? null,
                'recheck_on' => $data['recheck_on'] ?? null,
                'report_city' => (bool) ($data['report_city'] ?? false),
                'report_immigration' => (bool) ($data['report_immigration'] ?? false),
                'action_note' => $data['action_note'] ?? null,
                'signed_inspector' => $data['signed_inspector'] ?? $inspector->name,
                'signed_farm' => $data['signed_farm'] ?? null,
                'signed_worker' => $data['signed_worker'] ?? null,
                'signed_interpreter' => $data['signed_interpreter'] ?? null,
                'risk_score' => 0,
                'risk_level' => RiskLevel::Low,
            ]);

            $score = 0;
            $critical = false;

            foreach ($answers as $itemId => $answer) {
                $item = $items->get((int) $itemId);
                if ($item === null) {
                    continue;   // 꺼졌거나 없는 항목은 버린다
                }

                $value = is_array($answer) ? (string) ($answer['value'] ?? '') : (string) $answer;
                if (! array_key_exists($value, $item->section->options())) {
                    continue;   // 이 영역에 없는 보기는 버린다
                }

                $review->answers()->create([
                    'work_review_item_id' => $item->id,
                    'value' => $value,
                    'note' => is_array($answer) ? ($answer['note'] ?? null) : null,
                ]);

                if (! $item->scored) {
                    continue;   // 기록만 남기는 항목 (scored 주석 참조)
                }

                if ($item->isBad($value)) {
                    $score += self::BAD;
                    // 이탈 가능성이 미흡이면 다른 점수와 무관하게 고위험이다.
                    if ($item->code === WorkReviewItem::FLIGHT_RISK) {
                        $critical = true;
                    }
                } elseif ($item->isMiddling($value)) {
                    $score += self::MIDDLING;
                }
            }

            // 표에 드러나지 않아도 이 둘은 그 자체로 중대한 신호다.
            if ($review->wage_unpaid || $review->result === WorkReviewResult::SpecialCare) {
                $critical = true;
            }

            $review->forceFill([
                'risk_score' => $score,
                'risk_level' => $critical ? RiskLevel::High : RiskLevel::fromReviewScore($score),
            ])->save();

            return $review->refresh();
        });
    }
}
