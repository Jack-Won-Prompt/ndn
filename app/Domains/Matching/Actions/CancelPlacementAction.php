<?php

declare(strict_types=1);

namespace App\Domains\Matching\Actions;

use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Models\User;
use RuntimeException;

/**
 * 배정 취소 (업무흐름 §4 — 증빙: 변경 사유 기록).
 *
 * 취소하면 그 근로자는 다시 미배정이 되어 다른 수요의 후보로 잡힌다.
 * 사유를 남기는 것이 이 Action 의 핵심이다.
 */
class CancelPlacementAction
{
    /**
     * @throws RuntimeException 이미 취소된 건일 때
     */
    public function execute(Placement $placement, User $actor, ?string $reason = null): Placement
    {
        if (! $placement->status->canTransitionTo(PlacementStatus::Cancelled)) {
            throw new RuntimeException(
                "{$placement->status->label()} 상태에서는 취소할 수 없습니다."
            );
        }

        $from = $placement->status;

        $placement->forceFill([
            'status' => PlacementStatus::Cancelled,
            'note' => $reason,
        ])->save();

        activity('placement')
            ->performedOn($placement)
            ->causedBy($actor)
            ->withProperties(['from' => $from->value, 'reason' => $reason])
            ->log('배정 취소');

        return $placement->refresh();
    }
}
