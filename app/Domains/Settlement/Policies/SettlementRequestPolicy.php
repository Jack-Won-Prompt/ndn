<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Policies;

use App\Domains\Settlement\Models\SettlementRequest;
use App\Models\User;
use App\Shared\Enums\UserRole;

/**
 * 정착 서비스 인가 (CLAUDE.md §7-5: Global Scope + Policy 이중 방어).
 *
 * partner_agency 는 자신에게 배정된(assigned_agency_id 일치) 건만 열람·처리할 수 있다.
 * PartnerAgencyScope 가 쿼리에서 1차로 걸러내고, 이 Policy 가 직접 접근을 2차로 막는다.
 * ndn_admin 은 전건 접근 가능.
 */
class SettlementRequestPolicy
{
    /** 대리점 담당자가 이 건의 배정 대리점 소속인지 */
    private function belongsToAgency(User $user, SettlementRequest $request): bool
    {
        return $user->hasRole(UserRole::PartnerAgency->value)
            && $request->assigned_agency_id !== null
            && $user->assigned_agency_id === $request->assigned_agency_id;
    }

    public function view(User $user, SettlementRequest $request): bool
    {
        return $user->hasRole(UserRole::NdnAdmin->value) || $this->belongsToAgency($user, $request);
    }

    /** 상태 처리(진행/완료)는 배정 대리점만 */
    public function process(User $user, SettlementRequest $request): bool
    {
        return $this->belongsToAgency($user, $request);
    }

    public function uploadDocument(User $user, SettlementRequest $request): bool
    {
        return $this->belongsToAgency($user, $request);
    }

    public function downloadDocument(User $user, SettlementRequest $request): bool
    {
        return $this->view($user, $request);
    }
}
