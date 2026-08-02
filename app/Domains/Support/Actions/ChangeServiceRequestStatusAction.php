<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Support\Enums\ServiceRequestStatus;
use App\Domains\Support\Models\ServiceRequest;
use App\Domains\Support\Notifications\ServiceRequestCompletedNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * SR 상태 전이 + 적용 완료 시 등록자 이메일 발송.
 *
 * '적용 완료' 로 **새로 바뀌는 순간에만** 알림을 보낸다. 이미 완료된 SR 을 다시
 * 완료로 저장해도 중복 발송하지 않는다. 알림은 큐로 나가므로(§8) 트랜잭션이
 * 커밋된 뒤 보내야 워커가 아직 없는 행을 읽는 일이 없다.
 */
class ChangeServiceRequestStatusAction
{
    public function execute(ServiceRequest $sr, ServiceRequestStatus $target, User $actor): ServiceRequest
    {
        if ($sr->status !== $target && ! $sr->status->canTransitionTo($target)) {
            throw new RuntimeException("전이할 수 없는 상태입니다: {$sr->status->label()} → {$target->label()}");
        }

        $justCompleted = $target === ServiceRequestStatus::Completed
            && $sr->status !== ServiceRequestStatus::Completed;

        DB::transaction(function () use ($sr, $target, $actor) {
            $sr->status = $target;
            $sr->completed_at = $target === ServiceRequestStatus::Completed ? now() : null;
            $sr->completed_by = $target === ServiceRequestStatus::Completed ? $actor->id : null;

            // 담당자가 없던 SR 을 종료하면 처리한 사람이 담당자가 된다.
            if ($sr->assignee_user_id === null && $target->isClosed()) {
                $sr->assignee_user_id = $actor->id;
            }

            $sr->save();

            activity('service-request')
                ->performedOn($sr)
                ->causedBy($actor)
                ->withProperties(['to' => $target->value])
                ->log('SR 상태 변경');
        });

        // 커밋 뒤에 발송한다 — 큐 워커가 아직 커밋되지 않은 행을 읽는 일이 없도록.
        if ($justCompleted) {
            $sr->loadMissing('requester');
            $sr->requester?->notify(new ServiceRequestCompletedNotification(
                serviceRequestId: $sr->id,
                consoleUrl: url('admin'),
            ));
        }

        return $sr;
    }
}
