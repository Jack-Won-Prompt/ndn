<?php

declare(strict_types=1);

use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Http\Controllers\Api\Admin\CandidateAdminController;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * 현지 면접 평가 API (업무흐름 §2).
 */
beforeEach(function () {
    foreach (UserRole::values() as $role) {
        Role::findOrCreate($role, 'web');
    }

    $this->admin = User::factory()->create();
    $this->admin->assignRole(UserRole::NdnAdmin->value);
    Sanctum::actingAs($this->admin);
});

/** 항목별 동일 점수로 총점을 맞춘다 (4항목 × $each) */
function scoresOf(int $each): array
{
    return array_fill_keys(array_keys(CandidateAdminController::CRITERIA), $each);
}

it('평가 시트 정의를 서버가 내려준다', function () {
    Candidate::factory()->create();

    $meta = $this->getJson('/api/v1/admin/candidates')->assertOk()->json('meta');

    expect(collect($meta['criteria'])->pluck('key')->all())
        ->toBe(array_keys(CandidateAdminController::CRITERIA));
});

it('미평가 후보가 목록 맨 위에 온다', function () {
    Candidate::factory()->create(['status' => CandidateStatus::Passed]);
    $applied = Candidate::factory()->create(['status' => CandidateStatus::Applied]);

    $this->getJson('/api/v1/admin/candidates')
        ->assertOk()
        ->assertJsonPath('data.0.id', $applied->id);
});

it('총점이 높으면 합격으로 분류된다', function () {
    $candidate = Candidate::factory()->create(['status' => CandidateStatus::Applied]);

    // 4항목 × 20 = 80 → 합격(70 이상)
    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => scoresOf(20),
        'comment' => '적극적',
    ])->assertOk()->assertJsonPath('data.status', CandidateStatus::Passed->value);

    expect($candidate->refresh()->status)->toBe(CandidateStatus::Passed);
});

it('중간 점수는 보류가 되고 대기열 순번을 받는다', function () {
    $candidate = Candidate::factory()->create(['status' => CandidateStatus::Applied]);

    // 4항목 × 15 = 60 → 보류(50~69)
    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => scoresOf(15),
    ])->assertOk()->assertJsonPath('data.status', CandidateStatus::Held->value);

    expect($candidate->refresh()->queue_position)->not->toBeNull();
});

it('낮은 점수는 불합격이고 대기열에 들어가지 않는다', function () {
    $candidate = Candidate::factory()->create(['status' => CandidateStatus::Applied]);

    // 4항목 × 5 = 20 → 불합격
    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => scoresOf(5),
    ])->assertOk()->assertJsonPath('data.status', CandidateStatus::Rejected->value);

    expect($candidate->refresh()->queue_position)->toBeNull();
});

it('점수 항목이 빠지면 거부된다', function () {
    $candidate = Candidate::factory()->create();

    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => ['health' => 20],
    ])->assertStatus(422)->assertJsonValidationErrorFor('scores.attitude');
});

it('항목 만점을 넘는 점수는 거부된다', function () {
    $candidate = Candidate::factory()->create();

    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => scoresOf(CandidateAdminController::MAX_SCORE + 1),
    ])->assertStatus(422);
});

it('점수가 잘리지 않고 총점에 반영된다', function () {
    $candidate = Candidate::factory()->create(['status' => CandidateStatus::Applied]);

    // 중첩 규칙을 validated() 로 받으면 항목이 사라져 총점이 0 이 된다
    $response = $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => scoresOf(20),
    ])->assertOk();

    expect($response->json('data.total_score'))->toBe(80);
});

it('대기열 1순위를 합격으로 충원한다', function () {
    $held = Candidate::factory()->create([
        'status' => CandidateStatus::Held,
        'queue_position' => 1,
    ]);

    $this->postJson('/api/v1/admin/candidates/promote')
        ->assertOk()
        ->assertJsonPath('data.id', $held->id);

    expect($held->refresh()->status)->toBe(CandidateStatus::Passed);
});

it('대기열이 비면 충원할 수 없다', function () {
    $this->postJson('/api/v1/admin/candidates/promote')->assertStatus(422);
});

it('시청·농가는 후보자 평가에 접근할 수 없다 (모집은 NDN 업무)', function (UserRole $role) {
    $user = User::factory()->create();
    $user->assignRole($role->value);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/admin/candidates')->assertForbidden();
})->with([UserRole::CityOfficer, UserRole::FarmOwner]);
