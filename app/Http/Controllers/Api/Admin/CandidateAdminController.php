<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domains\Recruitment\Actions\EvaluateCandidateAction;
use App\Domains\Recruitment\Actions\PromoteFromWaitlistAction;
use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Domains\Recruitment\Models\EvaluationItem;
use App\Http\Controllers\Api\Admin\Concerns\ScopesPortalQueries;
use App\Http\Controllers\Controller;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 관리자 앱 — 현지 면접 평가 (업무흐름 §2).
 *
 * 송출국 현지에서 폰으로 평가 시트를 채우는 것이 원래 설계다("모바일 평가 시트").
 * 항목별 점수 합계로 합격/보류/불합격이 자동 분류되고, 보류자는 대기열 순번을 받는다.
 *
 * 후보자는 아직 배정 전이라 농가·지자체 스코프가 성립하지 않는다.
 * 모집·선발은 NDN 이 하는 일이므로 NDN 관리자만 접근한다.
 */
class CandidateAdminController extends Controller
{
    use ScopesPortalQueries;

    // 평가 항목은 콘솔에서 관리한다(EvaluationItem). 앱은 index 응답의 criteria 로
    // 시트를 그리므로 항목을 바꿔도 앱 배포가 필요 없다.

    public function index(Request $request): JsonResponse
    {
        // 모집·선발은 NDN 업무다. 시청·농가는 후보자 단계에 관여하지 않는다.
        $actor = $this->authorizeDecision($request);

        $query = Candidate::query()
            ->with('evaluations')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value()),
            )
            ->when(
                $request->filled('q'),
                fn ($q) => $q->where('name', 'like', '%'.$request->string('q')->value().'%'),
            )
            // 미평가(applied) 를 먼저, 보류는 대기열 순서대로
            ->orderByRaw("CASE WHEN status = 'applied' THEN 0 WHEN status = 'held' THEN 1 ELSE 2 END")
            ->orderByRaw('queue_position IS NULL')
            ->orderBy('queue_position')
            ->orderByDesc('id');

        $page = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => collect($page->items())
                ->map(fn (Candidate $c) => $this->present($c))->all(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'statuses' => array_map(
                    fn (CandidateStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    CandidateStatus::cases(),
                ),
                // 평가 시트 정의 — 앱에 항목을 하드코딩하지 않는다(콘솔에서 관리)
                'criteria' => EvaluationItem::sheet()
                    ->map(fn (EvaluationItem $i) => [
                        'key' => $i->key,
                        'label' => $i->label,
                        'hint' => $i->hint,
                        'max' => $i->max_score,
                    ])->all(),
                'total_max_score' => EvaluationItem::totalMaxScore(),
                'waitlist_count' => Candidate::query()->waitlist()->count(),
                'can_decide' => PortalScope::canDecide($actor),
            ],
        ]);
    }

    /** 면접 평가 기록 — 점수 합계로 합격/보류/불합격이 자동 결정된다 */
    public function evaluate(Request $request, int $candidate, EvaluateCandidateAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);

        $model = Candidate::find($candidate);
        abort_if($model === null, 404, '해당 후보자를 찾을 수 없습니다.');

        // 시트 항목·배점은 콘솔에서 바뀔 수 있으므로 매번 DB 에서 읽어 규칙을 만든다.
        $items = EvaluationItem::sheet();

        if ($items->isEmpty()) {
            return response()->json([
                'message' => '평가 항목이 없습니다. 콘솔에서 평가 항목을 먼저 등록하세요.',
            ], 422);
        }

        $rules = ['comment' => ['nullable', 'string', 'max:1000']];
        foreach ($items as $item) {
            $rules["scores.{$item->key}"] = ['required', 'integer', 'min:0', 'max:'.$item->max_score];
        }
        $request->validate($rules);

        // 중첩 규칙은 validated() 에서 잘려 나가므로 원본에서 항목만 추려 쓴다.
        $scores = collect((array) $request->input('scores', []))
            ->only($items->pluck('key')->all())
            ->map(fn ($v) => (int) $v)
            ->all();

        $evaluation = $action->execute(
            $model,
            $actor,
            $scores,
            $request->input('comment'),
        );

        return response()->json([
            'data' => $this->present($model->refresh()->load('evaluations')) + [
                'total_score' => $evaluation->total_score,
                'result' => $evaluation->result,
            ],
        ]);
    }

    /** 대기열 자동 충원 — 합격자 결원 발생 시 보류 1순위를 끌어올린다 */
    public function promote(Request $request, PromoteFromWaitlistAction $action): JsonResponse
    {
        $this->authorizeDecision($request);

        $promoted = $action->execute();

        if ($promoted === null) {
            return response()->json(['message' => '대기열에 후보자가 없습니다.'], 422);
        }

        return response()->json(['data' => $this->present($promoted->load('evaluations'))]);
    }

    private function present(Candidate $candidate): array
    {
        $latest = $candidate->relationLoaded('evaluations')
            ? $candidate->evaluations->last()
            : null;

        return [
            'id' => $candidate->id,
            'name' => $candidate->name,
            'nationality' => $candidate->nationality,
            'age' => $candidate->age,
            'gender' => $candidate->gender,
            'status' => $candidate->status->value,
            'status_label' => $candidate->status->label(),
            'queue_position' => $candidate->queue_position,
            'total_score' => $latest?->total_score,
            'evaluated_at' => $latest?->evaluated_at?->toIso8601String(),
            'comment' => $latest?->comment,
        ];
    }
}
