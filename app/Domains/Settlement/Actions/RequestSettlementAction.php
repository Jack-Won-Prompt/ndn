<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Actions;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Enums\SettlementType;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Shared\Enums\ConsentPurpose;
use RuntimeException;

/**
 * 근로자의 정착 서비스 신청 (업무흐름 §6).
 *
 * 통장·보험·통신·유심은 대리점(제3자)이 처리하므로, 신청 전에 제3자 제공
 * 동의가 있어야 한다(CLAUDE.md §7-4). 동의 없이 접수하면 이후 대리점 배정
 * 단계에서 막히거나 동의 없는 제공이 일어난다.
 *
 * 같은 유형을 중복 신청하지 못하게 한다 — 처리 중인 건이 있으면 그것을 쓴다.
 */
class RequestSettlementAction
{
    /** 접수 후 처리 기한(일) — 관리자 보드의 SLA 표시에 쓰인다. */
    private const SLA_DAYS = 7;

    /**
     * 신청에 필요한 동의가 모두 있는지.
     *
     * 정착 서비스는 **반드시 제휴 대리점이 처리**하므로 제3자 제공 동의까지
     * 있어야 한다. 서비스 이용 동의만 받고 접수하면, 나중에 대리점 배정
     * (AssignSettlementAction)에서 막혀 "접수는 됐는데 진행이 안 되는" 건이
     * 쌓인다. 그래서 접수 시점에 함께 확인한다.
     */
    public static function hasRequiredConsents(Worker $worker): bool
    {
        return $worker->hasActiveConsent(ConsentPurpose::SettlementService)
            && $worker->hasActiveConsent(ConsentPurpose::ThirdPartyAgency, 'partner_agency');
    }

    /**
     * @throws RuntimeException 동의가 없거나 이미 진행 중인 신청이 있을 때
     */
    public function execute(Worker $worker, SettlementType $type): SettlementRequest
    {
        if (! self::hasRequiredConsents($worker)) {
            throw new RuntimeException(
                '정착 서비스 이용과 제휴 대리점 제공에 대한 동의가 모두 필요합니다.'
            );
        }

        // 완료되지 않은 같은 유형 신청이 있으면 새로 만들지 않는다.
        $existing = SettlementRequest::where('worker_id', $worker->id)
            ->where('type', $type->value)
            ->where('status', '!=', SettlementStatus::Done->value)
            ->latest('id')
            ->first();

        if ($existing !== null) {
            throw new RuntimeException('이미 처리 중인 신청이 있습니다.');
        }

        $request = SettlementRequest::create([
            'worker_id' => $worker->id,
            'type' => $type,
            'status' => SettlementStatus::Received,
            'sla_due_at' => now()->addDays(self::SLA_DAYS),
        ]);

        activity('settlement')
            ->performedOn($request)
            ->withProperties(['type' => $type->value, 'worker_id' => $worker->id])
            ->log('정착 서비스 신청(근로자 앱)');

        return $request;
    }
}
