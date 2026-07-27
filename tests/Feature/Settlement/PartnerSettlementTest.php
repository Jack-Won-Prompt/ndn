<?php

declare(strict_types=1);

use App\Domains\Onboarding\Actions\GrantConsentAction;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Actions\AssignSettlementAction;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Settlement\Notifications\SettlementAssignedNotification;
use App\Models\User;
use App\Shared\Enums\ConsentPurpose;
use App\Shared\Enums\UserRole;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

/**
 * 제휴 대리점 알림 + 웹 처리 (CLAUDE.md §7-3·§7-4·§7-5, 업무흐름 §6-3).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

/** 동의를 갖춘 근로자 + 배정 대상 대리점 사용자 준비 */
function partnerFixture(int $agencyId = 10): array
{
    $partner = User::factory()->create(['assigned_agency_id' => $agencyId, 'name' => 'A보험대리점']);
    $partner->assignRole(UserRole::PartnerAgency->value);

    $worker = Worker::factory()->create();
    app(GrantConsentAction::class)->execute(
        $worker, ConsentPurpose::ThirdPartyAgency, ConsentPurpose::ThirdPartyAgency->value, 'partner_agency',
    );

    return [$partner, $worker];
}

it('배정하면 해당 대리점 사용자에게 알림이 발송된다', function () {
    Notification::fake();
    [$partner, $worker] = partnerFixture(10);
    $req = SettlementRequest::factory()->create(['worker_id' => $worker->id, 'status' => SettlementStatus::Received]);

    app(AssignSettlementAction::class)->execute($req, 10);

    Notification::assertSentTo($partner, SettlementAssignedNotification::class);
    expect($req->refresh()->status)->toBe(SettlementStatus::Assigned);
});

it('제3자 제공 동의가 없으면 배정이 거부되고 알림도 없다 (§7-4)', function () {
    Notification::fake();
    $partner = User::factory()->create(['assigned_agency_id' => 10]);
    $partner->assignRole(UserRole::PartnerAgency->value);
    $worker = Worker::factory()->create();   // 동의 없음
    $req = SettlementRequest::factory()->create(['worker_id' => $worker->id]);

    expect(fn () => app(AssignSettlementAction::class)->execute($req, 10))
        ->toThrow(RuntimeException::class);

    Notification::assertNothingSent();
});

it('대리점은 배정 건의 상태를 처리 중 → 완료로 전이할 수 있다', function () {
    [$partner, $worker] = partnerFixture(10);
    $req = SettlementRequest::factory()->assignedTo(10)->create([
        'worker_id' => $worker->id, 'status' => SettlementStatus::Assigned,
    ]);

    actingAs($partner);

    $this->post(route('portal.settlements.process', $req->id), [
        'target' => SettlementStatus::Processing->value, 'note' => '보험 가입 진행',
    ])->assertRedirect();
    expect($req->refresh()->status)->toBe(SettlementStatus::Processing);

    $this->post(route('portal.settlements.process', $req->id), [
        'target' => SettlementStatus::Done->value,
    ])->assertRedirect();
    $req->refresh();
    expect($req->status)->toBe(SettlementStatus::Done);
    expect($req->completed_at)->not->toBeNull();
    expect($req->partner_note)->toBe('보험 가입 진행');
});

it('대리점은 배정된 건 상세 화면을 열람할 수 있다', function () {
    [$partner, $worker] = partnerFixture(10);
    $req = SettlementRequest::factory()->assignedTo(10)->create([
        'worker_id' => $worker->id, 'status' => SettlementStatus::Assigned,
    ]);

    actingAs($partner);

    $this->get(route('portal.settlements.show', $req->id))
        ->assertOk()
        ->assertSee('배정 건')
        ->assertSee('상태 처리');
});

it('대리점은 미배정(다른 대리점) 건을 열람·처리할 수 없다 (스코프)', function () {
    [$partner] = partnerFixture(10);
    $other = SettlementRequest::factory()->assignedTo(20)->create(['status' => SettlementStatus::Assigned]);

    actingAs($partner);

    // 전역 스코프로 라우트 모델 바인딩이 못 찾음 → 404
    $this->get(route('portal.settlements.show', $other->id))->assertNotFound();
    $this->post(route('portal.settlements.process', $other->id), [
        'target' => SettlementStatus::Processing->value,
    ])->assertNotFound();
});

it('증빙 문서 업로드 후 다운로드에는 워터마크가 적용된다 (§7-5)', function () {
    Storage::fake('local');
    [$partner, $worker] = partnerFixture(10);
    $req = SettlementRequest::factory()->assignedTo(10)->create([
        'worker_id' => $worker->id, 'status' => SettlementStatus::Processing,
    ]);

    actingAs($partner);

    $this->post(route('portal.settlements.documents.store', $req->id), [
        'file' => UploadedFile::fake()->image('proof.png', 400, 300),
    ])->assertRedirect();

    $doc = $req->documents()->firstOrFail();
    Storage::disk('local')->assertExists($doc->disk_path);

    $res = $this->get(route('portal.settlements.documents.show', [$req->id, $doc->id]));
    $res->assertOk();
    // 워터마크 적용 시 PNG 로 재인코딩되어 반환된다.
    expect($res->headers->get('content-type'))->toContain('image/png');
});

it('대리점 목록 진입 시 배정 알림이 읽음 처리된다', function () {
    [$partner, $worker] = partnerFixture(10);
    $req = SettlementRequest::factory()->create(['worker_id' => $worker->id, 'status' => SettlementStatus::Received]);
    app(AssignSettlementAction::class)->execute($req, 10);   // 실제 알림 저장(database)

    expect($partner->unreadNotifications()->count())->toBe(1);

    actingAs($partner);
    $this->get(route('portal.settlements.index'))->assertOk();

    expect($partner->refresh()->unreadNotifications()->count())->toBe(0);
})->group('guard');
