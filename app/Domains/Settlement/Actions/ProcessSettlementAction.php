<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Actions;

use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use RuntimeException;

/**
 * 대리점이 배정 건의 처리 상태를 전이한다 (배정 → 처리 중 → 완료).
 *
 * 허용 전이는 SettlementStatus::canTransitionTo 로 강제한다.
 * 완료 시 completed_at 을 기록한다. 처리 메모(partner_note)는 선택.
 */
class ProcessSettlementAction
{
    public function execute(SettlementRequest $request, SettlementStatus $target, ?string $note = null): SettlementRequest
    {
        if (! $request->status->canTransitionTo($target)) {
            throw new RuntimeException("전이할 수 없는 상태입니다: {$request->status->value} → {$target->value}");
        }

        $request->status = $target;

        if ($note !== null && trim($note) !== '') {
            $request->partner_note = trim($note);
        }

        if ($target === SettlementStatus::Done) {
            $request->completed_at = now();
        }

        $request->save();

        return $request;
    }
}
