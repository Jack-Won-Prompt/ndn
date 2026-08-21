<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Support\Actions\UpdateSosStatusAction;
use App\Domains\Support\Enums\SosStatus;
use App\Domains\Support\Models\SosAlert;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Support\LocalTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * 긴급 SOS 상황판 (콘솔).
 *
 * 관리자 앱에는 있었지만 웹 콘솔에는 화면이 없었다 — 긴급 대응 기능인데 사무실
 * 화면에서 볼 수 없었다는 뜻이다.
 *
 * 미확인 건이 위로, 그 안에서 오래 방치된 것부터 온다. 좌표는 근로자가 SOS 를
 * 누른 그 순간의 값이며(§7-2) 여기서는 조회만 한다.
 */
class SosController extends Controller
{
    /** 미확인 우선 · 오래 방치된 순. */
    public static function rows(): array
    {
        // 근로자 소속(시·농가) = 확정 배정 → 농가 → 시. N+1 방지로 즉시 로딩.
        return SosAlert::query()
            ->with([
                'worker.placements' => fn ($q) => $q
                    ->where('status', PlacementStatus::Confirmed->value)->latest('id')->with('farm.city'),
                'acknowledgedBy:id,name',
            ])
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 WHEN status = 'acknowledged' THEN 1 ELSE 2 END")
            ->orderBy('alerted_at')
            ->limit(500)
            ->get()
            ->map(function (SosAlert $a) {
                $farm = $a->worker?->placements->first()?->farm;
                $minutes = $a->responseMinutes();

                return [
                    'id' => $a->id,
                    'worker_id' => $a->worker_id,
                    'worker' => $a->worker?->name ?? '—',
                    'nationality' => $a->worker?->nationality ?? '—',
                    'city' => $farm?->city?->name ?? '—',
                    'farm' => $farm?->name ?? '—',
                    'status' => $a->status->value,
                    'status_label' => $a->status->label(),
                    'alerted_at' => LocalTime::format($a->alerted_at),
                    // 미확인이면 지금까지 방치된 시간, 확인 후면 대응까지 걸린 시간.
                    'minutes' => $minutes,
                    'elapsed' => self::humanMinutes($minutes),
                    'coords' => $a->lat !== null && $a->lng !== null
                        ? number_format((float) $a->lat, 5).', '.number_format((float) $a->lng, 5)
                        : '—',
                    // 좌표가 없을 수 있다(실내·권한 거부). 그때는 지도를 열지 않는다.
                    'map_url' => $a->lat !== null && $a->lng !== null
                        ? 'https://www.google.com/maps?q='.$a->lat.','.$a->lng
                        : null,
                    'acknowledged_by' => $a->acknowledgedBy?->name ?? '—',
                    'acknowledged_at' => LocalTime::format($a->acknowledged_at) ?? '—',
                    'note' => $a->note ?? '',
                    // 표에 그리려면 '소속' 이 한 칸이어야 한다.
                    'belong' => trim(($farm?->city?->name ?? '-').' / '.($farm?->name ?? '-')),
                    // 미확인이 30분 넘게 방치되면 눈에 띄어야 한다.
                    'late' => $a->status === SosStatus::Open && $minutes !== null && $minutes >= 30 ? '지연' : '',
                    // 편집기가 없는 칸이라 눌러도 셀이 열리지 않는다 → 여는 버튼으로 쓴다.
                    'map' => $a->lat !== null && $a->lng !== null ? '지도 ▸' : '',
                ];
            })
            ->all();
    }

    /**
     * 표에서 체크한 건을 한 번에 확인·종료 처리한다.
     *
     * 표 안에는 버튼을 둘 수 없어(편집기 없는 칸은 글자만 그린다) 체크 → 툴바
     * 순서로 처리한다. 신고 내용 자체(발신 시각·좌표·근로자)는 근로자가 보낸
     * 것이라 손대지 않는다 — 여기서 하는 일은 대응 상태를 남기는 것뿐이다.
     *
     * 한 건이 막혀도 나머지는 진행한다. 이미 종료된 건이 섞였다고 통째로
     * 되돌아가면 무엇이 걸렸는지 찾기만 어려워진다.
     */
    public function bulkStatus(Request $request, UpdateSosStatusAction $action): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([SosStatus::Acknowledged->value, SosStatus::Closed->value])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:sos_alerts,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $target = SosStatus::from($data['status']);
        $done = 0;
        $failed = [];

        foreach (SosAlert::whereIn('id', $data['ids'])->get() as $sos) {
            try {
                $action->execute($sos, $target, Auth::user(), $data['note'] ?? null);
                $done++;
            } catch (RuntimeException $e) {
                $failed[] = '#'.$sos->id.' '.$e->getMessage();
            }
        }

        $word = $target === SosStatus::Acknowledged ? '확인' : '종료';

        return response()->json([
            'ok' => true,
            'message' => $failed === []
                ? "{$done}건을 {$word} 처리했습니다."
                : "{$done}건 {$word} · ".count($failed).'건 건너뜀 — '.implode(' / ', array_slice($failed, 0, 3)),
            'rows' => self::rows(),
            'open_count' => self::openCount(),
        ]);
    }

    /** 아직 아무도 확인하지 않은 건수 — 사이드바 배지. */
    public static function openCount(): int
    {
        return SosAlert::where('status', SosStatus::Open->value)->count();
    }

    /** 확인·종료 처리. */
    public function updateStatus(Request $request, SosAlert $sos, UpdateSosStatusAction $action): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in([SosStatus::Acknowledged->value, SosStatus::Closed->value])],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $action->execute($sos, SosStatus::from($data['status']), Auth::user(), $data['note'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'open_count' => self::openCount()]);
    }

    /**
     * 근로자 개인정보 열람 기록 (§7-6).
     *
     * 이 화면은 목록에 근로자 이름이 그대로 보인다. 목록을 여는 것도 열람이다.
     */
    public static function logAccess(User $actor, array $rows): void
    {
        $ids = array_values(array_unique(array_filter(array_column($rows, 'worker_id'))));
        if ($ids === []) {
            return;
        }

        activity('personal-data')
            ->causedBy($actor)
            ->withProperties(['worker_ids' => $ids, 'context' => 'console-sos', 'count' => count($ids)])
            ->log('근로자 개인정보 열람(SOS 상황판)');
    }

    /** '2시간 15분' 처럼 읽히게. 분만 쓰면 방치 시간이 한눈에 안 들어온다. */
    private static function humanMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes.'분';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        if ($hours < 24) {
            return $rest === 0 ? $hours.'시간' : $hours.'시간 '.$rest.'분';
        }

        return intdiv($hours, 24).'일 '.($hours % 24).'시간';
    }
}
