<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Monitoring\Models\LifeChecklistCheck;
use App\Domains\Monitoring\Models\LifeChecklistItem;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 한국 생활 체크리스트 — 근로자별 확인 현황.
 *
 * 근로자가 앱에서 직접 체크한다. 관리자는 여기서 아직 확인하지 않은 사람과
 * 항목을 보고 챙긴다. 관리자가 대신 체크하지는 않는다 — 본인 확인 기록이라
 * 대신 눌러 주면 기록의 뜻이 없어진다.
 */
class LifeChecklistController extends Controller
{
    /** 진행이 덜 된 근로자가 위로 오도록 정렬한 목록. */
    public static function rows(): array
    {
        $items = LifeChecklistItem::query()->active()->get();
        $total = $items->count();

        // 근로자 소속(시·농가) = 확정 배정 → 농가 → 시. N+1 방지로 즉시 로딩.
        $workers = Worker::query()
            ->whereIn('status', [WorkerStatus::Active->value])
            ->with(['placements' => function ($q) {
                $q->where('status', PlacementStatus::Confirmed->value)->latest('id')->with('farm.city');
            }])
            ->orderBy('name')
            ->limit(2000)
            ->get(['id', 'name', 'nationality', 'status']);

        // 근로자별 체크한 항목 id — 한 번에 받아 메모리에서 맞춘다.
        $checks = LifeChecklistCheck::query()
            ->whereIn('worker_id', $workers->pluck('id'))
            ->get(['worker_id', 'life_checklist_item_id', 'checked_at'])
            ->groupBy('worker_id');

        return $workers->map(function (Worker $w) use ($items, $total, $checks) {
            $mine = $checks->get($w->id) ?? collect();
            $done = $mine->pluck('life_checklist_item_id')->all();
            $pending = $items->whereNotIn('id', $done);

            $placement = $w->placements->first();
            $farm = $placement?->farm;

            return [
                'worker_id' => $w->id,
                'worker' => $w->name,
                'nationality' => $w->nationality,
                'city' => $farm?->city?->name ?? '—',
                'farm' => $farm?->name ?? '—',
                'done' => count($done),
                'total' => $total,
                'progress' => $total > 0 ? (int) round(count($done) / $total * 100) : 0,
                'state' => match (true) {
                    $total === 0 => '—',
                    count($done) === 0 => '미시작',
                    $pending->isEmpty() => '완료',
                    default => '진행 중',
                },
                'last_checked' => $mine->max('checked_at')?->timezone(config('ndn.timezone'))->format('Y-m-d H:i') ?? '—',
                // 남은 항목은 상세 모달에서 그대로 보여 준다.
                'pending' => $pending->pluck('label')->values()->all(),
            ];
        })
            // 덜 된 사람이 위로. 같은 진행률이면 이름순(위에서 이미 정렬했다).
            ->sortBy('progress')
            ->values()
            ->all();
    }

    /** 항목 문구 편집 — 번역은 실시간이므로 한국어만 고치면 된다. */
    public function updateItem(Request $request, LifeChecklistItem $item): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:200'],
            'hint' => ['nullable', 'string', 'max:300'],
            'active' => ['required', 'boolean'],
        ]);

        $item->update($data);

        return response()->json(['ok' => true]);
    }

    /** 항목 관리 탭에 뿌릴 원본 목록 (꺼진 것 포함). */
    public static function itemRows(): array
    {
        return LifeChecklistItem::query()
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (LifeChecklistItem $i) => [
                'id' => $i->id,
                'code' => $i->code,
                'label' => $i->label,
                'hint' => $i->hint ?? '',
                'active' => $i->active ? '사용' : '중지',
            ])
            ->all();
    }
}
