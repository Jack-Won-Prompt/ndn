<?php

declare(strict_types=1);

use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Domains\Recruitment\Models\EvaluationItem;
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

/**
 * 항목별로 배점의 $percent% 를 준다.
 *
 * 항목·배점은 콘솔에서 바뀌므로(EvaluationItem) 고정 점수로 쓰면 배점을 조정하는
 * 순간 테스트가 깨진다. 판정도 비율 기준이라 비율로 맞추는 편이 의도에 맞는다.
 *
 * @return array<string, int>
 */
function scoresAtPercent(int $percent): array
{
    return EvaluationItem::sheet()
        ->mapWithKeys(fn (EvaluationItem $i) => [$i->key => (int) floor($i->max_score * $percent / 100)])
        ->all();
}

it('평가 시트 정의를 서버가 내려준다', function () {
    Candidate::factory()->create();

    $meta = $this->getJson('/api/v1/admin/candidates')->assertOk()->json('meta');

    expect(collect($meta['criteria'])->pluck('key')->all())
        ->toBe(EvaluationItem::sheet()->pluck('key')->all());
    expect($meta['total_max_score'])->toBe(EvaluationItem::totalMaxScore());
});

it('평가 항목을 콘솔에서 바꾸면 시트도 따라 바뀐다', function () {
    Candidate::factory()->create();
    EvaluationItem::query()->update(['active' => false]);
    EvaluationItem::factory()->create(['key' => 'driving', 'label' => '운전 가능 여부', 'max_score' => 40]);

    $meta = $this->getJson('/api/v1/admin/candidates')->assertOk()->json('meta');

    expect(collect($meta['criteria'])->pluck('key')->all())->toBe(['driving']);
    expect($meta['total_max_score'])->toBe(40);
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

    // 만점의 80% → 합격(70% 이상)
    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => scoresAtPercent(80),
        'comment' => '적극적',
    ])->assertOk()->assertJsonPath('data.status', CandidateStatus::Passed->value);

    expect($candidate->refresh()->status)->toBe(CandidateStatus::Passed);
});

it('중간 점수는 보류가 되고 대기열 순번을 받는다', function () {
    $candidate = Candidate::factory()->create(['status' => CandidateStatus::Applied]);

    // 만점의 60% → 보류(50~69%)
    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => scoresAtPercent(60),
    ])->assertOk()->assertJsonPath('data.status', CandidateStatus::Held->value);

    expect($candidate->refresh()->queue_position)->not->toBeNull();
});

it('낮은 점수는 불합격이고 대기열에 들어가지 않는다', function () {
    $candidate = Candidate::factory()->create(['status' => CandidateStatus::Applied]);

    // 만점의 20% → 불합격
    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => scoresAtPercent(20),
    ])->assertOk()->assertJsonPath('data.status', CandidateStatus::Rejected->value);

    expect($candidate->refresh()->queue_position)->toBeNull();
});

it('점수 항목이 빠지면 거부된다', function () {
    $candidate = Candidate::factory()->create();

    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => ['health' => 20],
    ])->assertStatus(422)->assertJsonValidationErrorFor('scores.experience');
});

it('항목 만점을 넘는 점수는 거부된다', function () {
    $candidate = Candidate::factory()->create();

    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => scoresAtPercent(150),
    ])->assertStatus(422);
});

it('평가 항목이 하나도 없으면 평가할 수 없다', function () {
    EvaluationItem::query()->update(['active' => false]);
    $candidate = Candidate::factory()->create();

    $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", ['scores' => []])
        ->assertStatus(422);
});

it('점수가 잘리지 않고 총점에 반영된다', function () {
    $candidate = Candidate::factory()->create(['status' => CandidateStatus::Applied]);

    // 중첩 규칙을 validated() 로 받으면 항목이 사라져 총점이 0 이 된다
    $scores = scoresAtPercent(80);
    $response = $this->postJson("/api/v1/admin/candidates/{$candidate->id}/evaluate", [
        'scores' => $scores,
    ])->assertOk();

    expect($response->json('data.total_score'))->toBe(array_sum($scores));
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
