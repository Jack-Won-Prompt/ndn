<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Recruitment\Enums\ScreeningStatus;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Notifications\WorkerPassedNotification;
use App\Domains\Recruitment\Notifications\WorkerRejectedNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 가입 신청 선발 결정 — 합격 / 보류 / 불합격 (업무흐름 §2).
 *
 * 합격이 하는 일이 이 Action 의 핵심이다. **합격은 곧 가입 승인이다** —
 * 합격시켜 놓고 계정을 따로 열어 주는 절차를 두면 담당자가 반드시 한쪽을 잊는다.
 * 그래서 한 번에 계정을 활성화하고 합격 푸시를 보낸다.
 *
 * 보류는 아무것도 바꾸지 않는다. 판단을 미뤘다는 표시일 뿐이라 계정은 그대로
 * 승인 대기로 둔다.
 */
class ScreenWorkerAction
{
    /**
     * @throws RuntimeException 결정할 수 없는 값이거나 이미 처리된 계정일 때
     */
    public function execute(Worker $worker, ScreeningStatus $decision, ?string $note, User $admin): Worker
    {
        if (! in_array($decision, ScreeningStatus::decisions(), true)
            || $decision === ScreeningStatus::SupplementRequested) {
            // 보완 요청은 메일 발송이 따라붙어 RequestSupplementAction 이 따로 맡는다.
            throw new RuntimeException('여기서 처리할 수 없는 결정입니다.');
        }

        if (! $worker->status->isPending()) {
            throw new RuntimeException(
                "이미 처리된 신청입니다 (현재 {$worker->status->label()})."
            );
        }

        $decided = DB::transaction(function () use ($worker, $decision, $note, $admin) {
            $worker->forceFill([
                'screening_status' => $decision,
                'screening_note' => $note,
                'screened_at' => now(),
                'screened_by' => $admin->id,
            ]);

            // 합격 → 계정을 함께 연다. 불합격 → 닫는다. 보류 → 그대로 둔다.
            match ($decision) {
                ScreeningStatus::Passed => $worker->forceFill([
                    'status' => WorkerStatus::Active,
                    'approved_at' => now(),
                    'approved_by' => $admin->id,
                    // 보완 요청이 걸려 있었다면 함께 걷는다.
                    'supplement_items' => null,
                    'supplement_requested_at' => null,
                ]),
                ScreeningStatus::Failed => $worker->forceFill(['status' => WorkerStatus::Rejected]),
                default => null,
            };

            $worker->save();

            activity('worker-account')
                ->performedOn($worker)
                ->causedBy($admin)
                ->withProperties(['screening' => $decision->value, 'note' => $note])
                ->log("가입 신청 {$decision->label()}");

            return $worker;
        });

        // 알림은 커밋 뒤에 보낸다 — 롤백된 결정을 근로자에게 알리면 되돌릴 수 없다.
        match ($decision) {
            ScreeningStatus::Passed => $decided->notify(new WorkerPassedNotification($decided->locale ?? 'ko')),
            ScreeningStatus::Failed => $decided->notify(new WorkerRejectedNotification($decided->locale ?? 'ko')),
            default => null,
        };

        return $decided;
    }
}
