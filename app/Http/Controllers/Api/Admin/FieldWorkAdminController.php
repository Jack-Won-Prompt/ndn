<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Actions\RecordFarmVisitAction;
use App\Domains\Monitoring\Enums\FarmVisitStatus;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Monitoring\Models\FarmVisitPhoto;
use App\Domains\Monitoring\Models\InspectionCheckin;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Api\Admin\Concerns\ScopesPortalQueries;
use App\Http\Controllers\Controller;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 관리자 앱 — 현장 점검 (업무흐름 §7).
 *
 * 농가 방문 점검(사진 포함) · 근로자별 월별 인터뷰 · 점검자 GPS 체크인.
 * 현장에서 폰으로 하는 일이라 모바일이 웹보다 맞다.
 *
 * 위치정보(§7-2): 체크인만 좌표를 남긴다. 농가 방문 기록에는 좌표를 두지 않고
 * 현장 사진으로 증빙한다.
 */
class FieldWorkAdminController extends Controller
{
    use ScopesPortalQueries;

    /** 방문할 농가 목록 + 각 농가에 배정된 근로자 (인터뷰 대상) */
    public function farms(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $farms = PortalScope::farms(Farm::query(), $actor)
            ->with(['city:id,name'])
            ->orderBy('name')
            ->get();

        // 농가별 배정 근로자 — 방문 시 인터뷰할 대상
        $workers = PortalScope::workers(Worker::query(), $actor)
            ->with('placements:id,worker_id,farm_id,status')
            ->get();

        $this->logWorkerAccess($actor, $workers->pluck('id')->all(), 'field-farms');

        return response()->json([
            'data' => $farms->map(fn (Farm $farm) => [
                'id' => $farm->id,
                'name' => $farm->name,
                'city' => $farm->city?->name,
                'crop' => $farm->main_crop,
                'workers' => $workers
                    ->filter(fn (Worker $w) => $w->placements->contains(
                        fn ($p) => $p->farm_id === $farm->id && $p->status->value === 'confirmed'
                    ))
                    ->map(fn (Worker $w) => [
                        'id' => $w->id,
                        'name' => $w->name,
                        'nationality' => $w->nationality,
                    ])->values()->all(),
                'last_visited_on' => FarmVisit::where('farm_id', $farm->id)
                    ->latest('visited_on')->value('visited_on')?->toDateString(),
            ])->all(),
            'meta' => [
                'statuses' => array_map(
                    fn (FarmVisitStatus $s) => ['value' => $s->value, 'label' => $s->label()],
                    FarmVisitStatus::cases(),
                ),
                'items' => MonthlyInterview::ITEMS,
                'can_decide' => PortalScope::canDecide($actor),
            ],
        ]);
    }

    /** 방문 점검 이력 */
    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $query = FarmVisit::query()
            ->whereHas('farm', fn ($f) => PortalScope::farms($f, $actor))
            ->with(['farm:id,name', 'visitedBy:id,name', 'photos:id,farm_visit_id'])
            ->withCount('interviews')
            ->orderByDesc('visited_on')
            ->orderByDesc('id');

        $page = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => collect($page->items())
                ->map(fn (FarmVisit $v) => $this->present($v))->all(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'can_decide' => PortalScope::canDecide($actor),
            ],
        ]);
    }

    /**
     * 방문 점검 등록 (multipart).
     *
     * 사진은 photos[] 로, 근로자 인터뷰는 interviews[] JSON 으로 받는다.
     * 사진 저장은 private 디스크이며 경로만 DB 에 남는다.
     */
    public function store(Request $request, RecordFarmVisitAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);

        $data = $request->validate([
            'farm_id' => ['required', 'integer'],
            'visited_on' => ['required', 'date'],
            'farm_status' => ['nullable', 'in:normal,caution,issue'],
            'worker_status' => ['nullable', 'in:normal,caution,issue'],
            'worker_headcount' => ['nullable', 'integer', 'min:0'],
            'work_note' => ['nullable', 'string', 'max:2000'],
            'issue_note' => ['nullable', 'string', 'max:2000'],
            'action_note' => ['nullable', 'string', 'max:2000'],
            'memo' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['file', 'image', 'max:8192'],
            // 근로자별 6항목 인터뷰 (선택)
            'interviews' => ['nullable', 'array'],
            'interviews.*.worker_id' => ['required', 'integer'],
            'interviews.*.memo' => ['nullable', 'string', 'max:1000'],
            'interviews.*.items' => ['nullable', 'array'],
            'interviews.*.items.*' => ['boolean'],
        ]);

        $farm = PortalScope::farms(Farm::query()->whereKey($data['farm_id']), $actor)->first();
        abort_if($farm === null, 404, '해당 농가를 찾을 수 없습니다.');

        // validated() 는 규칙이 걸린 키만 돌려주므로 items 의 개별 항목이 잘려 나간다.
        // 검증은 위에서 하고 값은 원본에서 가져온다.
        // 아울러 스코프 밖 근로자가 인터뷰 대상에 섞이지 않게 거른다.
        $interviews = collect((array) $request->input('interviews', []))
            ->filter(fn ($entry) => is_array($entry) && isset($entry['worker_id']))
            ->filter(fn (array $entry) => PortalScope::canSeeWorker($actor, (int) $entry['worker_id']))
            ->map(fn (array $entry) => [
                'worker_id' => (int) $entry['worker_id'],
                // 6항목만 남긴다 — 알 수 없는 키가 리스크 계산에 섞이지 않게
                'items' => collect((array) ($entry['items'] ?? []))
                    ->only(MonthlyInterview::ITEMS)
                    ->map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN))
                    ->all(),
                'memo' => $entry['memo'] ?? null,
            ])
            ->values()
            ->all();

        $visit = $action->execute(
            $farm,
            $actor,
            $data,
            $request->file('photos') ?? [],
            $interviews,
        );

        return response()->json([
            'data' => $this->present(
                $visit->load(['farm:id,name', 'visitedBy:id,name', 'photos'])->loadCount('interviews')
            ),
        ], 201);
    }

    /** 현장 사진 스트리밍 — private 저장이라 서명 없이 직접 접근할 수 없다 */
    public function photo(Request $request, int $visit, int $photo): StreamedResponse
    {
        $actor = $this->actor($request);

        $record = FarmVisitPhoto::query()
            ->whereKey($photo)
            ->where('farm_visit_id', $visit)
            ->whereHas('visit.farm', fn ($f) => PortalScope::farms($f, $actor))
            ->first();

        abort_if($record === null, 404);

        return Storage::disk('local')->response(
            $record->path,
            $record->original_name,
            ['Content-Type' => $record->mime ?? 'application/octet-stream'],
        );
    }

    /**
     * 점검자 GPS 체크인 (§7-2 — 허용된 두 곳 중 하나).
     *
     * 좌표는 이 요청 본문으로만 받고, 방문 증빙 목적으로만 저장한다.
     */
    public function checkin(Request $request): JsonResponse
    {
        $actor = $this->authorizeDecision($request);

        $data = $request->validate([
            'worker_id' => ['required', 'integer'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'memo' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_unless(
            PortalScope::canSeeWorker($actor, $data['worker_id']),
            404,
            '해당 근로자를 찾을 수 없습니다.',
        );

        $checkin = InspectionCheckin::create([
            'worker_id' => $data['worker_id'],
            'inspector_user_id' => $actor->id,
            'lat' => $data['lat'],
            'lng' => $data['lng'],
            'checked_in_at' => now(),
            'memo' => $data['memo'] ?? null,
        ]);

        $this->logWorkerAccess($actor, [$checkin->worker_id], 'inspection-checkin');

        return response()->json([
            'data' => [
                'id' => $checkin->id,
                'worker_id' => $checkin->worker_id,
                'checked_in_at' => $checkin->checked_in_at->toIso8601String(),
            ],
        ], 201);
    }

    private function present(FarmVisit $visit): array
    {
        return [
            'id' => $visit->id,
            'farm' => $visit->farm?->name,
            'farm_id' => $visit->farm_id,
            'visited_on' => $visit->visited_on?->toDateString(),
            'visited_by' => $visit->visitedBy?->name,
            'farm_status' => $visit->farm_status->value,
            'farm_status_label' => $visit->farm_status->label(),
            'worker_status' => $visit->worker_status->value,
            'worker_status_label' => $visit->worker_status->label(),
            'worker_headcount' => $visit->worker_headcount,
            'work_note' => $visit->work_note,
            'issue_note' => $visit->issue_note,
            'action_note' => $visit->action_note,
            'memo' => $visit->memo,
            'interview_count' => $visit->interviews_count ?? 0,
            'photos' => $visit->relationLoaded('photos')
                ? $visit->photos->map(fn (FarmVisitPhoto $p) => [
                    'id' => $p->id,
                    'url' => url("/api/v1/admin/farm-visits/{$visit->id}/photos/{$p->id}"),
                ])->all()
                : [],
        ];
    }
}
