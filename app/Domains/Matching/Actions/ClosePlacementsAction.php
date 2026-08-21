<?php

declare(strict_types=1);

namespace App\Domains\Matching\Actions;

use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * 배정을 접는다 — 농가 삭제·근로자 삭제·배정 직접 삭제가 모두 이 길을 쓴다.
 *
 * 세 곳에서 같은 일을 따로 적어 두면 한 곳만 고쳐지고 나머지는 옛 방식으로 남는다.
 * 실제로 그럴 뻔했다 — 농가 삭제와 근로자 삭제가 같은 순서를 각자 적고 있었다.
 *
 * 순서가 핵심이다.
 *
 *   1) 살아 있는(제안·확정) 배정을 **먼저 취소**한다. 취소를 거쳐야
 *      - 근로자가 미배정으로 풀려 다른 농가에 넣을 수 있고,
 *      - 농가 정원이 실제로 비고,
 *      - 왜 빠졌는지가 감사 기록에 남는다.
 *      바로 지우면 사람만 소리 없이 사라진다(업무흐름 §4).
 *   2) 입국 기록을 먼저 걷는다 — 배정을 접은 뒤에는 어느 배정에 딸린 것인지
 *      찾을 수 없다.
 *   3) 마지막에 배정을 접는다.
 *
 * 전부 soft delete 다. 잘못 지웠을 때 되돌릴 수 있어야 하고, 누가 어디에
 * 배정됐다가 어떻게 정리됐는지가 증빙으로 남아야 한다.
 */
class ClosePlacementsAction
{
    /**
     * @param  Collection<int, Placement>  $placements
     * @return array<string, int> cancelled·arrivals·placements
     */
    public function execute(Collection $placements, User $actor, string $reason): array
    {
        if ($placements->isEmpty()) {
            return ['cancelled' => 0, 'arrivals' => 0, 'placements' => 0];
        }

        $cancel = app(CancelPlacementAction::class);

        $live = $placements->filter(fn (Placement $p) => in_array($p->status, [
            PlacementStatus::Proposed,
            PlacementStatus::Confirmed,
        ], true));

        foreach ($live as $placement) {
            $cancel->execute($placement, $actor, $reason);
        }

        $ids = $placements->pluck('id')->all();

        return [
            'cancelled' => $live->count(),
            'arrivals' => (int) ArrivalRecord::whereIn('placement_id', $ids)->delete(),
            'placements' => (int) Placement::whereIn('id', $ids)->delete(),
        ];
    }
}
