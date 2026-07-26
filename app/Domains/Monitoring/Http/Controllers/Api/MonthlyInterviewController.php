<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Http\Controllers\Api;

use App\Domains\Monitoring\Actions\SubmitSelfAssessmentAction;
use App\Domains\Monitoring\Enums\InterviewSource;
use App\Domains\Monitoring\Http\Requests\StoreSelfAssessmentRequest;
use App\Domains\Monitoring\Http\Resources\MonthlyInterviewResource;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * 근로자 앱 — 근로 생활 평가 (CLAUDE.md §9, 업무흐름 §7).
 *
 * 인증된 Worker 본인의 평가만 조회·제출한다(스코프 자동 보장).
 */
class MonthlyInterviewController extends Controller
{
    /** 본인 평가 이력 (점검자 방문 + 자가 평가 모두, 최신순) */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $interviews = MonthlyInterview::query()
            ->where('worker_id', $worker->id)
            ->orderByDesc('interviewed_on')
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        // 이번 달 자가 평가를 이미 냈는지 — 앱이 "제출/수정" 버튼을 가르는 데 쓴다.
        $selfThisMonth = $interviews->first(
            fn (MonthlyInterview $i) => $i->source === InterviewSource::Self
                && $i->interviewed_on?->isSameMonth(now())
        );

        return MonthlyInterviewResource::collection($interviews)
            ->additional(['meta' => [
                'self_submitted_this_month' => $selfThisMonth !== null,
                'items' => MonthlyInterview::ITEMS,
            ]]);
    }

    /** 자가 평가 제출 (같은 달 재제출 시 갱신) */
    public function store(
        StoreSelfAssessmentRequest $request,
        SubmitSelfAssessmentAction $action,
    ): MonthlyInterviewResource {
        /** @var Worker $worker */
        $worker = $request->user();

        $interview = $action->execute(
            $worker,
            $request->only(MonthlyInterview::ITEMS),
            $request->input('memo'),
        );

        return new MonthlyInterviewResource($interview);
    }
}
