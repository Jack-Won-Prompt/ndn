<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Actions;

use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use RuntimeException;

/**
 * 정착 처리보드 칸반 단계 이동 (업무흐름 §6-3).
 * 완료 처리 시 근로자 앱에 즉시 반영될 completed_at 을 기록한다.
 */
class MoveSettlementStageAction
{
    public function execute(SettlementRequest $request, SettlementStatus $target): SettlementRequest
    {
        if (! $request->status->canTransitionTo($target)) {
            throw new RuntimeException("전이할 수 없는 단계입니다: {$request->status->value} → {$target->value}");
        }

        $request->update([
            'status' => $target,
            'completed_at' => $target === SettlementStatus::Done ? now() : null,
        ]);

        return $request;
    }
}
