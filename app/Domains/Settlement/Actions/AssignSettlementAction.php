<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Actions;

use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Settlement\Notifications\SettlementAssignedNotification;
use App\Models\User;
use App\Shared\Enums\ConsentPurpose;
use App\Shared\Enums\UserRole;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * 정착 서비스 건을 제휴 대리점에 배정한다 (업무흐름 §6-3).
 *
 * 준수(§7-4): 근로자의 제3자 제공(대리점) 동의가 있어야 배정할 수 있다. 동의 없는
 * 건은 배정 자체가 거부되어 대리점 포털에 노출되지 않는다.
 */
class AssignSettlementAction
{
    /** 기본 SLA (영업일 근사: 3일) */
    private const SLA_DAYS = 3;

    public function execute(SettlementRequest $request, int $agencyId): SettlementRequest
    {
        $worker = $request->worker()->first();

        if ($worker === null || ! $worker->hasActiveConsent(ConsentPurpose::ThirdPartyAgency, 'partner_agency')) {
            throw new RuntimeException('제3자 제공 동의가 없어 대리점에 배정할 수 없습니다 (§7-4).');
        }

        if (! $request->status->canTransitionTo(SettlementStatus::Assigned)
            && $request->status !== SettlementStatus::Assigned) {
            throw new RuntimeException("배정할 수 없는 상태입니다: {$request->status->value}");
        }

        $request->update([
            'assigned_agency_id' => $agencyId,
            'assigned_at' => now(),
            'status' => SettlementStatus::Assigned,
            'sla_due_at' => now()->addDays(self::SLA_DAYS),
        ]);

        $this->notifyAgency($agencyId, $request);

        return $request;
    }

    /**
     * 배정된 대리점(assigned_agency_id 일치 partner_agency 사용자들)에게 알림 발송.
     * §7-3: 알림 본문에 개인정보 없음(건수 + 서비스 유형 + 로그인 링크). 큐 처리.
     */
    private function notifyAgency(int $agencyId, SettlementRequest $request): void
    {
        // whereHas 로 역할을 확인 — spatie role() 스코프는 역할 미존재 시 예외를 던지므로 사용하지 않는다.
        $recipients = User::where('assigned_agency_id', $agencyId)
            ->whereHas('roles', fn ($q) => $q->where('name', UserRole::PartnerAgency->value))
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new SettlementAssignedNotification(1, $request->type->label()),
        );
    }
}
