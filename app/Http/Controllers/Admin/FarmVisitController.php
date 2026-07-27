<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Actions\RecordFarmVisitAction;
use App\Domains\Monitoring\Enums\FarmVisitStatus;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Monitoring\Models\FarmVisitPhoto;
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

    /** 방문 상세 (사진 URL 포함) — 상세 모달용. */
    public function show(FarmVisit $farmVisit): JsonResponse
    {
        $farmVisit->load(['farm', 'visitedBy', 'photos']);

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
        ]);
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
