<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Monitoring\Actions\RecordFarmVisitAction;
use App\Domains\Monitoring\Enums\FarmVisitStatus;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Monitoring\Models\FarmVisitPhoto;
use App\Domains\Monitoring\Models\WorkReview;
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
                // 표에는 참/거짓이 아니라 읽을 글자가 필요하다 (엑셀로도 그대로 나간다).
                'headcount_label' => $v->worker_headcount === null ? '-' : $v->worker_headcount.'명',
                'photos_label' => $v->photos_count > 0 ? $v->photos_count.'장' : '',
                'issue_label' => filled($v->issue_note) ? '있음' : '',
                // 편집기가 없는 칸이라 눌러도 셀이 열리지 않는다 → 상세를 여는 자리로 쓴다.
                'detail' => '상세 ▸',
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

    /** 특정 농가의 배정 확정 근로자 (방문 대상 명단). */
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
        ]);

        $farm = Farm::findOrFail($data['farm_id']);

        $visit = $action->execute($farm, Auth::user(), $data, $request->file('photos', []));

        return response()->json(['ok' => true, 'id' => $visit->id]);
    }

    /** 방문 상세 (사진 URL + 이 방문에 묶인 근무상태 점검표) — 상세 모달용. */
    public function show(FarmVisit $farmVisit): JsonResponse
    {
        $farmVisit->load(['farm', 'visitedBy', 'photos', 'workReviews.worker']);

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
            'reviews' => $farmVisit->workReviews->map(fn (WorkReview $r) => $this->reviewRow($r, true))->all(),
        ]);
    }

    /** 특정 근로자의 근무상태 점검 이력 (최신순). */
    public function workerHistory(Worker $worker): JsonResponse
    {
        $history = WorkReview::where('worker_id', $worker->id)
            ->with('farm')
            ->latest('reviewed_at')->latest('id')->limit(60)->get()
            ->map(function (WorkReview $r) {
                $row = $this->reviewRow($r);
                $row['farm'] = $r->farm?->name;

                return $row;
            })->all();

        return response()->json(['worker' => $worker->name, 'history' => $history]);
    }

    /** 점검표 1건을 표시용 배열로 변환. */
    private function reviewRow(WorkReview $r, bool $withWorker = false): array
    {
        $row = [
            'id' => $r->id,
            'date' => $r->reviewed_at?->timezone(config('ndn.timezone'))->format('Y-m-d'),
            'type' => $r->review_type->label(),
            'result' => $r->result->label(),
            'risk' => $r->risk_level->label(),
            'risk_level' => $r->risk_level->value,
            'score' => $r->risk_score,
        ];
        if ($withWorker) {
            $row['worker_id'] = $r->worker_id;
            $row['worker'] = $r->worker?->name ?? '—';
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
