<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use Illuminate\Support\Facades\DB;

/**
 * 결원 발생 시 보류 대기열에서 순번대로 자동 충원 (업무흐름 §2-4, §4-1).
 */
class PromoteFromWaitlistAction
{
    /**
     * 대기열 최선순위 보류자를 합격으로 승격한다. 대기열이 비었으면 null.
     */
    public function execute(): ?Candidate
    {
        return DB::transaction(function () {
            $next = Candidate::query()->waitlist()->lockForUpdate()->first();

            if ($next === null) {
                return null;
            }

            $next->update([
                'status' => CandidateStatus::Passed,
                'queue_position' => null,
            ]);

            return $next;
        });
    }
}
