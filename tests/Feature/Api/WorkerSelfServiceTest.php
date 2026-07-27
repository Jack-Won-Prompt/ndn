<?php

declare(strict_types=1);

use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Onboarding\Actions\GrantConsentAction;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Actions\AssignSettlementAction;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Enums\SettlementType;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Models\User;
use App\Shared\Enums\ConsentPurpose;
use Laravel\Sanctum\Sanctum;

/**
 * 근로자 셀프 서비스 — 정착 신청 · 동의 관리 · 내 배정/입국 (업무흐름 §4·§5·§6, §7-4).
 */
function actingWorker(): Worker
{
    $worker = Worker::factory()->create();
    Sanctum::actingAs($worker);

    return $worker;
}

/** 신청에 필요한 동의 2종 — 서비스 이용 + 제휴 대리점 제공 */
function grantSettlementConsent(Worker $worker): void
{
    $grant = app(GrantConsentAction::class);

    $grant->execute(
        $worker,
        ConsentPurpose::SettlementService,
        ConsentPurpose::SettlementService->value,
    );
    $grant->execute(
        $worker,
        ConsentPurpose::ThirdPartyAgency,
        ConsentPurpose::ThirdPartyAgency->value,
        'partner_agency',
    );
}

// ── 정착 서비스 신청 ────────────────────────────────────────────────────

it('동의 없이는 정착 서비스를 신청할 수 없다 (§7-4)', function () {
    actingWorker();

    $response = $this->postJson('/api/v1/settlements', [
        'type' => SettlementType::Bank->value,
    ])->assertStatus(422);

    expect($response->json('message'))->toContain('동의');
    expect(SettlementRequest::count())->toBe(0);
});

it('동의가 있으면 정착 서비스를 신청할 수 있다', function () {
    $worker = actingWorker();
    grantSettlementConsent($worker);

    $this->postJson('/api/v1/settlements', ['type' => SettlementType::Bank->value])
        ->assertCreated()
        ->assertJsonPath('data.type', SettlementType::Bank->value)
        ->assertJsonPath('data.status', SettlementStatus::Received->value);

    expect(SettlementRequest::where('worker_id', $worker->id)->count())->toBe(1);
});

it('같은 유형을 중복 신청할 수 없다', function () {
    $worker = actingWorker();
    grantSettlementConsent($worker);

    $this->postJson('/api/v1/settlements', ['type' => SettlementType::Bank->value])
        ->assertCreated();
    $this->postJson('/api/v1/settlements', ['type' => SettlementType::Bank->value])
        ->assertStatus(422);

    expect(SettlementRequest::count())->toBe(1);
});

it('완료된 건은 같은 유형을 다시 신청할 수 있다', function () {
    $worker = actingWorker();
    grantSettlementConsent($worker);

    SettlementRequest::create([
        'worker_id' => $worker->id,
        'type' => SettlementType::Bank,
        'status' => SettlementStatus::Done,
    ]);

    $this->postJson('/api/v1/settlements', ['type' => SettlementType::Bank->value])
        ->assertCreated();
});

it('본인 신청만 목록에 보이고 대리점 정보는 내려가지 않는다', function () {
    $other = Worker::factory()->create();
    SettlementRequest::create([
        'worker_id' => $other->id,
        'type' => SettlementType::Telecom,
        'status' => SettlementStatus::Received,
    ]);

    $worker = actingWorker();
    grantSettlementConsent($worker);
    SettlementRequest::create([
        'worker_id' => $worker->id,
        'type' => SettlementType::Bank,
        'status' => SettlementStatus::Assigned,
        'assigned_agency_id' => 99,
    ]);

    $response = $this->getJson('/api/v1/settlements')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.has_consent', true);

    // 대리점 배정은 운영 내부 정보다
    expect($response->json('data.0'))->not->toHaveKey('assigned_agency_id');
    expect($response->json('meta.in_progress'))->toContain(SettlementType::Bank->value);
});

// ── 동의 관리 ───────────────────────────────────────────────────────────

it('동의 목록은 모든 목적을 상태와 함께 보여준다', function () {
    actingWorker();

    $data = $this->getJson('/api/v1/consents')->assertOk()->json('data');

    expect($data)->toHaveCount(count(ConsentPurpose::cases()))
        ->and(collect($data)->every(fn ($row) => $row['granted'] === false))->toBeTrue();
});

it('근로자가 직접 동의하고 철회할 수 있다', function () {
    $worker = actingWorker();
    $purpose = ConsentPurpose::SettlementService->value;

    $this->postJson('/api/v1/consents/grant', ['purpose' => $purpose])->assertOk();
    expect($worker->refresh()->hasActiveConsent(ConsentPurpose::SettlementService))->toBeTrue();

    $this->postJson('/api/v1/consents/revoke', ['purpose' => $purpose])->assertOk();
    expect($worker->refresh()->hasActiveConsent(ConsentPurpose::SettlementService))->toBeFalse();
});

it('철회해도 기록은 지우지 않고 철회 시각을 남긴다 (증빙)', function () {
    $worker = actingWorker();
    $purpose = ConsentPurpose::Notification->value;

    $this->postJson('/api/v1/consents/grant', ['purpose' => $purpose])->assertOk();
    $this->postJson('/api/v1/consents/revoke', ['purpose' => $purpose])->assertOk();

    $record = $worker->consents()->where('purpose', $purpose)->latest('id')->first();

    expect($record)->not->toBeNull()
        ->and($record->revoked_at)->not->toBeNull()
        ->and($record->granted_at)->not->toBeNull();
});

it('동의를 철회하면 정착 신청이 다시 막힌다', function () {
    $worker = actingWorker();
    grantSettlementConsent($worker);

    $this->postJson('/api/v1/settlements', ['type' => SettlementType::Usim->value])
        ->assertCreated();

    $this->postJson('/api/v1/consents/revoke', [
        'purpose' => ConsentPurpose::SettlementService->value,
    ])->assertOk();

    $this->postJson('/api/v1/settlements', ['type' => SettlementType::Insurance->value])
        ->assertStatus(422);
});

// ── 내 배정·입국 ────────────────────────────────────────────────────────

it('배정 전에는 data 가 null 이다', function () {
    actingWorker();

    $this->getJson('/api/v1/my/placement')->assertOk()->assertJsonPath('data', null);
});

it('제안 단계의 배정은 아직 보이지 않는다', function () {
    $worker = actingWorker();
    Placement::factory()->create([
        'worker_id' => $worker->id,
        'status' => PlacementStatus::Proposed,
    ]);

    $this->getJson('/api/v1/my/placement')->assertOk()->assertJsonPath('data', null);
});

it('확정된 배정과 입국 일정을 조회한다', function () {
    $worker = actingWorker();
    $farm = Farm::factory()->create(['name' => '햇살농장', 'main_crop' => '딸기']);
    $placement = Placement::factory()->confirmed()->create([
        'worker_id' => $worker->id,
        'farm_id' => $farm->id,
    ]);
    ArrivalRecord::factory()->create([
        'placement_id' => $placement->id,
        'flight_no' => 'VN409',
    ]);

    $this->getJson('/api/v1/my/placement')
        ->assertOk()
        ->assertJsonPath('data.farm', '햇살농장')
        ->assertJsonPath('data.crop', '딸기')
        ->assertJsonPath('data.arrival.flight_no', 'VN409')
        ->assertJsonPath('data.arrival.step', 0);
});

it('다른 근로자의 배정은 보이지 않는다', function () {
    $other = Worker::factory()->create();
    Placement::factory()->confirmed()->create(['worker_id' => $other->id]);

    actingWorker();

    $this->getJson('/api/v1/my/placement')->assertOk()->assertJsonPath('data', null);
});

it('픽업 담당자 개인정보는 내려가지 않는다', function () {
    $worker = actingWorker();
    $placement = Placement::factory()->confirmed()->create(['worker_id' => $worker->id]);
    ArrivalRecord::factory()->create([
        'placement_id' => $placement->id,
        'pickup_user_id' => User::factory()->create(['name' => '김담당'])->id,
    ]);

    $body = $this->getJson('/api/v1/my/placement')->assertOk()->getContent();

    expect($body)->not->toContain('김담당');
});

it('인증 없이는 접근할 수 없다', function (string $path) {
    $this->getJson($path)->assertUnauthorized();
})->with(['/api/v1/settlements', '/api/v1/consents', '/api/v1/my/placement']);

// ── 동의 → 대리점 배정 연결 (회귀) ──────────────────────────────────────

it('앱에서 제3자 제공에 동의하면 대리점 배정까지 이어진다', function () {
    $worker = actingWorker();

    // 앱 화면과 동일한 경로로 동의
    $this->postJson('/api/v1/consents/grant', [
        'purpose' => ConsentPurpose::SettlementService->value,
    ])->assertOk();
    $this->postJson('/api/v1/consents/grant', [
        'purpose' => ConsentPurpose::ThirdPartyAgency->value,
    ])->assertOk();

    $id = $this->postJson('/api/v1/settlements', [
        'type' => SettlementType::Bank->value,
    ])->assertCreated()->json('data.id');

    // AssignSettlementAction 은 agency_type='partner_agency' 로 동의를 확인한다.
    // 앱 동의가 그 조건을 만족하지 못하면 여기서 막힌다.
    $assigned = app(AssignSettlementAction::class)
        ->execute(SettlementRequest::findOrFail($id), 7);

    expect($assigned->assigned_agency_id)->toBe(7)
        ->and($worker->refresh()->hasActiveConsent(
            ConsentPurpose::ThirdPartyAgency,
            'partner_agency',
        ))->toBeTrue();
});

it('제3자 제공 동의가 없으면 신청 단계에서 막힌다 (배정까지 가서 막히지 않게)', function () {
    $worker = actingWorker();

    // 서비스 이용 동의만
    $this->postJson('/api/v1/consents/grant', [
        'purpose' => ConsentPurpose::SettlementService->value,
    ])->assertOk();

    $this->postJson('/api/v1/settlements', ['type' => SettlementType::Bank->value])
        ->assertStatus(422);

    expect(SettlementRequest::where('worker_id', $worker->id)->count())->toBe(0);
});

it('제3자 제공 동의를 철회하면 신청이 다시 막힌다', function () {
    $worker = actingWorker();
    grantSettlementConsent($worker);

    $this->postJson('/api/v1/consents/revoke', [
        'purpose' => ConsentPurpose::ThirdPartyAgency->value,
    ])->assertOk();

    $this->postJson('/api/v1/settlements', ['type' => SettlementType::Telecom->value])
        ->assertStatus(422);
});
