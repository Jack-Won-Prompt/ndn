<?php

declare(strict_types=1);

namespace App\Domains\Matching\Actions;

use App\Domains\Arrival\Enums\ArrivalDocument;
use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 배정 확정 (CLAUDE.md §4, 업무흐름 §4→§5).
 *
 * 확정과 동시에 입국 기록을 만들어 다음 단계(입국·이송)로 넘긴다. 두 작업은
 * 한 트랜잭션으로 묶어, 확정됐는데 입국 기록이 없는 상태가 생기지 않게 한다.
 */
class ConfirmPlacementAction
{
    /**
     * @throws RuntimeException 확정할 수 없는 상태일 때
     */
    public function execute(Placement $placement, User $actor): Placement
    {
        if (! $placement->status->canTransitionTo(PlacementStatus::Confirmed)) {
            throw new RuntimeException(
                "{$placement->status->label()} 상태에서는 확정할 수 없습니다."
            );
        }

        return DB::transaction(function () use ($placement, $actor) {
            $placement->forceFill([
                'status' => PlacementStatus::Confirmed,
                'confirmed_at' => now(),
                'confirmed_by' => $actor->id,
            ])->save();

            // 입국 기록이 없으면 만든다 (재확정 시 중복 생성 방지)
            ArrivalRecord::firstOrCreate(
                ['placement_id' => $placement->id],
                [
                    'status' => ArrivalStatus::Scheduled,
                    'documents' => ArrivalDocument::emptyChecklist(),
                ],
            );

            activity('placement')
                ->performedOn($placement)
                ->causedBy($actor)
                ->log('배정 확정');

            return $placement->refresh();
        });
    }
}
