<?php

declare(strict_types=1);

namespace App\Domains\Arrival\Actions;

use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Arrival\Models\ArrivalRecord;
use App\Models\User;
use RuntimeException;

/**
 * 입국 단계 진행 (CLAUDE.md §4, 업무흐름 §5).
 *
 * 단계를 건너뛸 수 없고, 공항 도착 확인 전에는 필수 서류(여권·E-8·항공권)가
 * 모두 확인돼 있어야 한다. 각 단계의 확인 시각을 함께 기록해 증빙을 남긴다.
 *
 * 위치는 기록하지 않는다(§7-2) — "언제·누가 확인했는가"만 남긴다.
 */
class AdvanceArrivalStageAction
{
    /**
     * @throws RuntimeException 단계를 건너뛰거나 서류가 미비할 때
     */
    public function execute(ArrivalRecord $record, ArrivalStatus $target, User $actor): ArrivalRecord
    {
        if (! $record->status->canTransitionTo($target)) {
            throw new RuntimeException(
                "{$record->status->label()} → {$target->label()} 로는 바로 넘어갈 수 없습니다."
            );
        }

        // 공항 도착 확인 시점에 서류가 갖춰져 있어야 이후 절차가 막히지 않는다.
        if ($target === ArrivalStatus::Arrived && ! $record->hasRequiredDocuments()) {
            $missing = implode(', ', $record->missingRequiredDocuments());

            throw new RuntimeException("필수 서류가 확인되지 않았습니다: {$missing}");
        }

        $timestampColumn = match ($target) {
            ArrivalStatus::Arrived => 'arrived_at',
            ArrivalStatus::PickedUp => 'picked_up_at',
            ArrivalStatus::HandedOver => 'handed_over_at',
            default => null,
        };

        $record->status = $target;
        if ($timestampColumn !== null) {
            $record->{$timestampColumn} = now();
        }
        $record->save();

        activity('arrival')
            ->performedOn($record)
            ->causedBy($actor)
            ->withProperties(['to' => $target->value])
            ->log('입국 단계 진행');

        return $record->refresh();
    }
}
