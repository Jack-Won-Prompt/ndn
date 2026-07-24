<?php

declare(strict_types=1);

namespace App\Domains\Demand\Policies;

use App\Domains\Demand\Models\DemandRequest;
use App\Models\User;
use App\Shared\Enums\UserRole;

/**
 * 수요 신청 인가 (CLAUDE.md §11: 인가는 Policy 에서, 컨트롤러에서 Gate 직접 호출 지양).
 *
 * - 농가(farm_owner): 자기 농가의 신청만 열람·생성·수정
 * - 시청(city_officer): 자기 시(city) 관할 신청 열람·취합
 * - NDN 관리자: 전체
 */
class DemandRequestPolicy
{
    /** NDN 관리자는 모든 것을 통과 */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isRole(UserRole::NdnAdmin) ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->isRole(UserRole::FarmOwner)
            || $user->isRole(UserRole::CityOfficer);
    }

    public function view(User $user, DemandRequest $demand): bool
    {
        return $this->ownsFarm($user, $demand)
            || $this->overseesCity($user, $demand);
    }

    public function create(User $user): bool
    {
        return $user->isRole(UserRole::FarmOwner);
    }

    /** 농가는 draft 상태의 자기 신청만 수정 가능 */
    public function update(User $user, DemandRequest $demand): bool
    {
        return $this->ownsFarm($user, $demand)
            && $demand->status->isEditableByFarm();
    }

    public function submit(User $user, DemandRequest $demand): bool
    {
        return $this->ownsFarm($user, $demand)
            && $demand->status->isEditableByFarm();
    }

    /** 취합은 시청 담당자만 */
    public function aggregate(User $user, DemandRequest $demand): bool
    {
        return $this->overseesCity($user, $demand);
    }

    private function ownsFarm(User $user, DemandRequest $demand): bool
    {
        return $user->isRole(UserRole::FarmOwner)
            && $demand->farm?->owner_user_id === $user->id;
    }

    private function overseesCity(User $user, DemandRequest $demand): bool
    {
        // 시청 담당자와 시(city) 매핑은 후속 슬라이스에서 city_officer↔city 연결로 정교화한다.
        // 지금은 역할 보유 여부로만 판정한다.
        return $user->isRole(UserRole::CityOfficer);
    }
}
