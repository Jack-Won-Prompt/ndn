<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Monitoring\Actions\RecordFarmVisitAction;
use App\Domains\Monitoring\Enums\FarmVisitStatus;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Monitoring\Models\FarmVisitPhoto;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 본사 월별 농가 방문 점검 (콘솔, ndn_admin).
 *
 * 농가 방문 기록(농가 상태·근무 현황·애로사항·조치)과 현장 사진(private) 등록·조회.
 */
class FarmVisitController extends Controller
{
    private const DISK = 'local';

    /** 월별 인터뷰 6항목 한국어 라벨 (true=양호). */
    public const ITEM_LABELS = [
        'pay_received' => '급여 수령',
        'no_discrimination' => '차별 없음',
        'follows_rules' => '생활 규칙',
        'adapts_group' => '단체생활',
        'health_ok' => '건강',
        'no_flight_signs' => '이탈징후 없음',
    ];

    /** 방문 점검 목록. */
    public static function rows(): array
    {
        return FarmVisit::with(['farm', 'visitedBy'])->withCount('photos')
            ->latest('visited_on')->latest('id')->limit(1000)->get()
            ->map(fn (FarmVisit $v) => [
                'id' => $v->id,
                'farm' => $v->farm?->name ?? '—',
                'visited_on' => $v->visited_on?->format('Y-m-d'),
                'inspector' => $v->visitedBy?->name ?? '—',
                'farm_status' => $v->farm_status->value,
                'farm_status_label' => $v->farm_status->label(),
                'worker_status' => $v->worker_status->value,
                'worker_status_label' => $v->worker_status->label(),
                'headcount' => $v->worker_headcount,
                'photos' => $v->photos_count,
                'has_issue' => filled($v->issue_note),
            ])->all();
    }

    public static function farmOptions(): array
    {
        return Farm::orderBy('name')->get(['id', 'name'])
            ->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->all();
    }

    public static function statusOptions(): array
    {
        return FarmVisitStatus::options();
    }

    /** 6항목 라벨 목록 [key,label] (폼 렌더용). */
    public static function itemLabels(): array
    {
        return array_map(fn ($k, $v) => ['key' => $k, 'label' => $v], array_keys(self::ITEM_LABELS), self::ITEM_LABELS);
    }

    /** 특정 농가의 배정 확정 근로자 (방문 시 인터뷰 대상). */
    public function workers(Farm $farm): JsonResponse
    {
        return response()->json(['workers' => $this->farmWorkers($farm)]);
    }

    /** @return array<int, array{id:int,name:string,nationality:string}> */
    private function farmWorkers(Farm $farm): array
    {
        return $farm->placements()
            ->where('status', PlacementStatus::Confirmed->value)
            ->with('worker')
            ->get()
            ->pluck('worker')
            ->filter()
            ->unique('id')
            ->map(fn (Worker $w) => ['id' => $w->id, 'name' => $w->name, 'nationality' => $w->nationality])
            ->values()
            ->all();
    }

    /** 방문 점검 등록 (사진 다중 업로드 포함). */
    public function store(Request $request, RecordFarmVisitAction $action): JsonResponse
    {
        $data = $request->validate([
            'farm_id' => ['required', 'integer', 'exists:farms,id'],
            'visited_on' => ['required', 'date'],
            'farm_status' => ['required', Rule::enum(FarmVisitStatus::class)],
            'worker_status' => ['required', Rule::enum(FarmVisitStatus::class)],
            'worker_headcount' => ['nullable', 'integer', 'min:0', 'max:999'],
            'work_note' => ['nullable', 'string', 'max:2000'],
            'issue_note' => ['nullable', 'string', 'max:2000'],
            'action_note' => ['nullable', 'string', 'max:2000'],
            'memo' => ['nullable', 'string', 'max:2000'],
            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*' => ['file', 'image', 'max:10240'], // 장당 10MB
            'interviews' => ['nullable', 'array'],
        ]);

        $farm = Farm::findOrFail($data['farm_id']);
        $interviews = $this->parseInterviews($request, $farm);

        $visit = $action->execute($farm, Auth::user(), $data, $request->file('photos', []), $interviews);

        return response()->json(['ok' => true, 'id' => $visit->id]);
    }

    /**
     * 요청의 근로자별 인터뷰를 파싱한다. 보안상 그 농가 배정 근로자에 한해서만 허용한다.
     * 체크된 항목=양호(true), 미체크=이상(false).
     *
     * @return array<int, array{worker_id:int, items:array<string,bool>, memo:?string}>
     */
    private function parseInterviews(Request $request, Farm $farm): array
    {
        $raw = $request->input('interviews', []);
        if (! is_array($raw)) {
            return [];
        }
        $allowed = collect($this->farmWorkers($farm))->pluck('id')->all();

        $out = [];
        foreach ($raw as $workerId => $entry) {
            $wid = (int) $workerId;
            if (! in_array($wid, $allowed, true) || ! is_array($entry)) {
                continue;
            }
            $items = [];
            foreach (MonthlyInterview::ITEMS as $item) {
                $items[$item] = filter_var($entry[$item] ?? false, FILTER_VALIDATE_BOOLEAN);
            }
            $memo = isset($entry['memo']) ? mb_substr(trim((string) $entry['memo']), 0, 1000) : null;
            $out[] = ['worker_id' => $wid, 'items' => $items, 'memo' => $memo ?: null];
        }

        return $out;
    }

    /** 방문 상세 (사진 URL + 근로자별 인터뷰 포함) — 상세 모달용. */
    public function show(FarmVisit $farmVisit): JsonResponse
    {
        $farmVisit->load(['farm', 'visitedBy', 'photos', 'interviews.worker']);

        return response()->json([
            'id' => $farmVisit->id,
            'farm' => $farmVisit->farm?->name ?? '—',
            'visited_on' => $farmVisit->visited_on?->format('Y-m-d'),
            'inspector' => $farmVisit->visitedBy?->name ?? '—',
            'farm_status' => $farmVisit->farm_status->label(),
            'worker_status' => $farmVisit->worker_status->label(),
            'headcount' => $farmVisit->worker_headcount,
            'work_note' => $farmVisit->work_note,
            'issue_note' => $farmVisit->issue_note,
            'action_note' => $farmVisit->action_note,
            'memo' => $farmVisit->memo,
            'photos' => $farmVisit->photos->map(fn (FarmVisitPhoto $p) => [
                'url' => route('admin.farm-visits.photo', ['farmVisit' => $farmVisit->id, 'photo' => $p->id]),
                'name' => $p->original_name,
            ])->all(),
            'interviews' => $farmVisit->interviews->map(fn (MonthlyInterview $iv) => $this->interviewRow($iv, true))->all(),
        ]);
    }

    /** 특정 근로자의 인터뷰 이력 (방문 점검 + 자가 평가 포함, 최신순). */
    public function workerHistory(Worker $worker): JsonResponse
    {
        $history = MonthlyInterview::where('worker_id', $worker->id)
            ->with('farmVisit.farm')
            ->latest('interviewed_on')->latest('id')->limit(60)->get()
            ->map(function (MonthlyInterview $iv) {
                $row = $this->interviewRow($iv);
                $row['source'] = $iv->source?->label() ?? '—';
                $row['farm'] = $iv->farmVisit?->farm?->name;

                return $row;
            })->all();

        return response()->json(['worker' => $worker->name, 'history' => $history]);
    }

    /** 인터뷰 1건을 표시용 배열로 변환 (6항목 양호/이상 + 리스크). */
    private function interviewRow(MonthlyInterview $iv, bool $withWorker = false): array
    {
        $items = [];
        foreach (self::ITEM_LABELS as $key => $label) {
            $items[] = ['label' => $label, 'ok' => (bool) $iv->{$key}];
        }
        $row = [
            'id' => $iv->id,
            'date' => $iv->interviewed_on?->format('Y-m-d'),
            'risk' => $iv->risk_level?->label() ?? '—',
            'risk_level' => $iv->risk_level?->value ?? 'low',
            'items' => $items,
            'memo' => $iv->memo,
        ];
        if ($withWorker) {
            $row['worker_id'] = $iv->worker_id;
            $row['worker'] = $iv->worker?->name ?? '—';
        }

        return $row;
    }

    /** 현장 사진 스트리밍 (private · ndn_admin 전용 라우트). */
    public function photo(FarmVisit $farmVisit, FarmVisitPhoto $photo): StreamedResponse
    {
        abort_unless($photo->farm_visit_id === $farmVisit->id, 404);
        abort_unless(Storage::disk(self::DISK)->exists($photo->path), 404);

        return Storage::disk(self::DISK)->response(
            $photo->path,
            $photo->original_name,
            ['Content-Type' => $photo->mime ?: 'application/octet-stream'],
            'inline',
        );
    }
}
