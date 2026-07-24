<?php

declare(strict_types=1);

use App\Domains\Settlement\Models\SettlementRequest;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;

use function Pest\Laravel\actingAs;

/**
 * 대리점 스코프 가드 (CLAUDE.md §7-5, §10).
 *
 * partner_agency 는 자신에게 배정된 SettlementRequest 만 조회할 수 있고,
 * 미배정 건은 쿼리 결과에 아예 나타나지 않아야 한다.
 *
 * 절대 삭제 금지 가드 테스트.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

it('대리점은 자신에게 배정된 정착 신청만 조회한다', function () {
    // 대리점 A 사용자 (assigned_agency_id = 10)
    $partner = User::factory()->create(['assigned_agency_id' => 10]);
    $partner->assignRole(UserRole::PartnerAgency->value);

    // 배정 건 2개 + 다른 대리점(20) 건 3개
    SettlementRequest::factory()->count(2)->assignedTo(10)->create();
    SettlementRequest::factory()->count(3)->assignedTo(20)->create();

    actingAs($partner);

    $visible = SettlementRequest::all();

    expect($visible)->toHaveCount(2);
    expect($visible->pluck('assigned_agency_id')->unique()->all())->toBe([10]);
});

it('미배정 건은 대리점이 직접 id 로 조회해도 찾을 수 없다', function () {
    $partner = User::factory()->create(['assigned_agency_id' => 10]);
    $partner->assignRole(UserRole::PartnerAgency->value);

    $other = SettlementRequest::factory()->assignedTo(20)->create();

    actingAs($partner);

    // 전역 스코프가 걸려 있어 다른 대리점 건은 find 로도 안 잡힌다.
    expect(SettlementRequest::find($other->id))->toBeNull();
});

it('NDN 관리자는 전역 스코프의 제약을 받지 않는다', function () {
    $admin = User::factory()->create();
    $admin->assignRole(UserRole::NdnAdmin->value);

    SettlementRequest::factory()->assignedTo(10)->create();
    SettlementRequest::factory()->assignedTo(20)->create();

    actingAs($admin);

    // partner_agency 역할이 아니므로 스코프가 적용되지 않아 전부 보인다.
    expect(SettlementRequest::count())->toBe(2);
})->group('guard');
