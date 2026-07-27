<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;

/**
 * 관리자 앱의 역할별 조회 범위 (CLAUDE.md §7-4, §7-5).
 *
 * 관리자 API 의 모든 목록·상세 쿼리는 반드시 이 헬퍼를 통과해야 한다.
 * 역할별로 볼 수 있는 근로자 집합이 다르기 때문이다.
 *
 *   ndn_admin    — 전체
 *   city_officer — 자기 지자체(city_id) 소속 농가에 배정된 근로자
 *   farm_owner   — 자기 농가(owner_user_id)에 배정된 근로자
 *
 * 배정 관계는 placements 테이블이 기준이다. 아직 배정되지 않은 근로자는
 * ndn_admin 에게만 보인다 — 시청·농가는 자기와 무관한 사람을 볼 이유가 없다.
 *
 * 스코프를 우회하는 raw 쿼리를 쓰지 말 것. 가드 테스트(PortalScopeTest)가
 * 역할별 격리를 검증한다.
 */
class PortalScope
{
    /**
     * 근로자 쿼리에 역할 범위를 적용한다.
     *
     * @param  Builder<Worker>  $query
     */
    public static function workers(Builder $query, User $user): Builder
    {
        return match (self::roleOf($user)) {
            UserRole::NdnAdmin => $query,

            UserRole::CityOfficer => $query->whereHas(
                'placements',
                fn (Builder $p) => $p->whereHas(
                    'farm',
                    fn (Builder $f) => $f->where('city_id', $user->city_id),
                ),
            ),

            UserRole::FarmOwner => $query->whereHas(
                'placements',
                fn (Builder $p) => $p->whereHas(
                    'farm',
                    fn (Builder $f) => $f->where('owner_user_id', $user->id),
                ),
            ),

            // 허용 역할이 아니면 아무것도 보이지 않는다 (미들웨어가 먼저 막지만 이중 방어)
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * worker_id 를 가진 테이블(민원·정착·SOS·온보딩 등)에 역할 범위를 적용한다.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function byWorker(Builder $query, User $user): Builder
    {
        if (self::roleOf($user) === UserRole::NdnAdmin) {
            return $query;
        }

        return $query->whereHas(
            'worker',
            fn (Builder $w) => self::workers($w, $user),
        );
    }

    /**
     * 농가 쿼리에 역할 범위를 적용한다.
     *
     * @param  Builder<Farm>  $query
     */
    public static function farms(Builder $query, User $user): Builder
    {
        return match (self::roleOf($user)) {
            UserRole::NdnAdmin => $query,
            UserRole::CityOfficer => $query->where('city_id', $user->city_id),
            UserRole::FarmOwner => $query->where('owner_user_id', $user->id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * 배정(Placement) 쿼리에 역할 범위를 적용한다. 입국 관리가 이걸 탄다.
     *
     * @param  Builder<Placement>  $query
     */
    public static function placements(Builder $query, User $user): Builder
    {
        return match (self::roleOf($user)) {
            UserRole::NdnAdmin => $query,

            UserRole::CityOfficer => $query->whereHas(
                'farm',
                fn (Builder $f) => $f->where('city_id', $user->city_id),
            ),

            UserRole::FarmOwner => $query->whereHas(
                'farm',
                fn (Builder $f) => $f->where('owner_user_id', $user->id),
            ),

            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * 이 사용자가 해당 근로자를 볼 수 있는지 (상세 조회·상태 변경 전 확인).
     */
    public static function canSeeWorker(User $user, int $workerId): bool
    {
        return self::workers(
            Worker::query()->whereKey($workerId),
            $user,
        )->exists();
    }

    /** 상태를 바꿀 수 있는 역할인지 — 승인·검수 등 판단은 NDN 관리자만 한다. */
    public static function canDecide(User $user): bool
    {
        return self::roleOf($user) === UserRole::NdnAdmin;
    }

    private static function roleOf(User $user): ?UserRole
    {
        return $user->primaryPortalRole();
    }
}
