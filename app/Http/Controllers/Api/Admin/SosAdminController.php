<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domains\Support\Actions\UpdateSosStatusAction;
use App\Domains\Support\Enums\SosStatus;
use App\Domains\Support\Models\SosAlert;
use App\Http\Controllers\Api\Admin\Concerns\ScopesPortalQueries;
use App\Http\Controllers\Controller;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 관리자 앱 — SOS 상황판 (업무흐름 §8, §7 긴급 24시간 대응).
 *
 * 미확인 건이 위로 오도록 정렬해, 방치된 긴급 건이 바로 눈에 띄게 한다.
 * 좌표는 근로자가 SOS 를 누른 그 순간의 값이며(§7-2) 여기서 조회만 한다.
 */
class SosAdminController extends Controller
{
    use ScopesPortalQueries;

    public function index(Request $request): JsonResponse
    {
        $actor = $this->actor($request);

        $query = PortalScope::byWorker(SosAlert::query(), $actor)
            ->with('worker:id,name,nationality')
            ->when(
                $request->filled('status'),
                fn ($q) => $q->where('status', $request->string('status')->value()),
            )
            // 미확인(open) 을 최우선으로, 그 안에서 오래된 것부터 — 방치 시간이 긴 순.
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 WHEN status = 'acknowledged' THEN 1 ELSE 2 END")
            ->orderBy('alerted_at');

        $page = $query->paginate($this->perPage($request));

        $this->logWorkerAccess($actor, $page->pluck('worker_id')->all(), 'sos-list');

        return response()->json([
            'data' => collect($page->items())->map(fn (SosAlert $a) => $this->present($a))->all(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                // 상황판 배지 — 미확인 건수
                'open_count' => PortalScope::byWorker(SosAlert::query(), $actor)
                    ->where('status', SosStatus::Open->value)->count(),
                'can_decide' => PortalScope::canDecide($actor),
            ],
        ]);
    }

    /** 확인·종료 처리 */
    public function updateStatus(Request $request, int $sos, UpdateSosStatusAction $action): JsonResponse
    {
        $actor = $this->authorizeDecision($request);

        $alert = PortalScope::byWorker(SosAlert::query()->whereKey($sos), $actor)->first();
        abort_if($alert === null, 404, '해당 SOS 를 찾을 수 없습니다.');

        $data = $request->validate([
            'status' => ['required', 'in:acknowledged,closed'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($alert, SosStatus::from($data['status']), $actor, $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($alert->refresh()->load('worker:id,name,nationality'))]);
    }

    private function present(SosAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'worker_id' => $alert->worker_id,
            'worker_name' => $alert->worker?->name,
            'nationality' => $alert->worker?->nationality,
            'status' => $alert->status->value,
            'status_label' => $alert->status->label(),
            'alerted_at' => $alert->alerted_at?->toIso8601String(),
            // 좌표가 없을 수도 있다(실내·권한 거부). 앱은 지도 대신 안내를 띄운다.
            'lat' => $alert->lat !== null ? (float) $alert->lat : null,
            'lng' => $alert->lng !== null ? (float) $alert->lng : null,
            'response_minutes' => $alert->responseMinutes(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'note' => $alert->note,
        ];
    }
}
