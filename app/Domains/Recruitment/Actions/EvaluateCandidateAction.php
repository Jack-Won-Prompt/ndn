<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
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
    /** 합격 기준(총점), 보류 기준(총점) */
    private const PASS_THRESHOLD = 70;

    private const HOLD_THRESHOLD = 50;

    /**
     * @param  array<string, int>  $scores  항목별 점수 (합계로 판정)
     */
    public function execute(Candidate $candidate, User $interviewer, array $scores, ?string $comment = null): InterviewEvaluation
    {
        $total = array_sum($scores);

        $result = match (true) {
            $total >= self::PASS_THRESHOLD => CandidateStatus::Passed,
            $total >= self::HOLD_THRESHOLD => CandidateStatus::Held,
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
