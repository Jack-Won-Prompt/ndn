<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Support\Enums\SosStatus;
use App\Domains\Support\Models\SosAlert;
use App\Models\User;
use RuntimeException;

/**
 * SOS 대응 상태 변경 (업무흐름 §7 — 긴급 24시간 대응).
 *
 * 누가 언제 확인했는지를 남기는 것이 이 Action 의 핵심이다. 긴급 건이 방치되지
 * 않았음을 증빙해야 하기 때문이다.
 */
class UpdateSosStatusAction
{
    /**
     * @throws RuntimeException 허용되지 않는 상태 전이일 때
     */
    public function execute(SosAlert $alert, SosStatus $target, User $actor, ?string $note = null): SosAlert
    {
        if (! $alert->status->canTransitionTo($target)) {
            throw new RuntimeException(
                "{$alert->status->label()} → {$target->label()} 전이는 허용되지 않습니다."
            );
        }

        $alert->status = $target;

        if ($target === SosStatus::Acknowledged) {
            $alert->acknowledged_at = now();
            $alert->acknowledged_by = $actor->id;
        }

        if ($target === SosStatus::Closed) {
            $alert->closed_at = now();
        }

        if ($note !== null && $note !== '') {
            $alert->note = $note;
        }

        $alert->save();

        activity('sos')
            ->performedOn($alert)
            ->causedBy($actor)
            ->withProperties(['to' => $target->value])
            ->log('SOS 상태 변경');

        return $alert->refresh();
    }
}
