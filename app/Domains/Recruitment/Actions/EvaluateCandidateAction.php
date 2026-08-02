<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Domains\Recruitment\Models\EvaluationItem;
use App\Domains\Recruitment\Models\InterviewEvaluation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 면접 평가 기록 + 결과 분류 (CLAUDE.md §4, 업무흐름 §2).
 *
 * 항목별 점수 합계로 합격/보류/불합격을 정하고, 보류자는 대기열 순번을 자동 부여한다.
 */
class EvaluateCandidateAction
{
    /**
     * 합격·보류 기준 — 만점 대비 **비율**(%).
     *
     * 평가 항목과 배점은 콘솔에서 바뀔 수 있으므로(EvaluationItem) 절대 점수로
     * 판정하면 항목을 하나 지우는 순간 기준이 무너진다. 비율로 본다.
     */
    private const PASS_PERCENT = 70;

    private const HOLD_PERCENT = 50;

    /**
     * @param  array<string, int>  $scores  항목별 점수 (합계 비율로 판정)
     */
    public function execute(Candidate $candidate, User $interviewer, array $scores, ?string $comment = null): InterviewEvaluation
    {
        $total = array_sum($scores);
        $max = EvaluationItem::totalMaxScore();

        // 항목이 하나도 없으면 판정 근거가 없다 — 보류로 두고 관리자가 항목을 채우게 한다.
        $percent = $max > 0 ? ($total / $max) * 100 : 0;

        $result = match (true) {
            $max <= 0 => CandidateStatus::Held,
            $percent >= self::PASS_PERCENT => CandidateStatus::Passed,
            $percent >= self::HOLD_PERCENT => CandidateStatus::Held,
            default => CandidateStatus::Rejected,
        };

        return DB::transaction(function () use ($candidate, $interviewer, $scores, $total, $result, $comment) {
            $evaluation = InterviewEvaluation::create([
                'candidate_id' => $candidate->id,
                'interviewer_user_id' => $interviewer->id,
                'scores' => $scores,
                'total_score' => $total,
                'result' => $result->value,
                'comment' => $comment,
                'evaluated_at' => now(),
            ]);

            $candidate->status = $result;

            // 보류자는 대기열 맨 뒤 순번 부여, 그 외는 대기열에서 제외
            if ($result === CandidateStatus::Held) {
                $maxPos = Candidate::where('status', CandidateStatus::Held->value)->max('queue_position') ?? 0;
                $candidate->queue_position = $maxPos + 1;
            } else {
                $candidate->queue_position = null;
            }

            $candidate->save();

            return $evaluation;
        });
    }
}
