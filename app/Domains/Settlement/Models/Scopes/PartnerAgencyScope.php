<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Models\Scopes;

use App\Models\User;
use App\Shared\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * 대리점 포털 전역 스코프 (CLAUDE.md §7-5: Global Scope + Policy 이중 방어).
 *
 * 현재 인증 사용자가 partner_agency 역할이면, 자신에게 배정된
 * (assigned_agency_id 일치) SettlementRequest 만 쿼리 결과에 포함된다.
 * 배정되지 않은 건은 애초에 결과에서 제외되어 존재조차 노출되지 않는다.
 */
class PartnerAgencyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        if (! $user->hasRole(UserRole::PartnerAgency->value)) {
            return;
        }

        // 배정 대리점이 없으면 아무것도 못 본다 (null 은 스코프상 미일치).
        $builder->where(
            $model->qualifyColumn('assigned_agency_id'),
            $user->assigned_agency_id
        );
    }
}
