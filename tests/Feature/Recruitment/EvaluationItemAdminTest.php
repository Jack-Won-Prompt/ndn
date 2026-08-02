<?php

declare(strict_types=1);

use App\Domains\Recruitment\Models\EvaluationItem;
use App\Domains\Recruitment\Models\InterviewEvaluation;
use App\Models\User;
use App\Shared\Enums\UserRole;
use Database\Seeders\EvaluationItemSeeder;
use Database\Seeders\RoleSeeder;

/**
 * 면접 평가 체크리스트 항목 관리 — 콘솔에서 추가·수정·삭제 (업무흐름 §2).
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->evalAdmin = User::factory()->create();
    $this->evalAdmin->assignRole(UserRole::NdnAdmin->value);
});

it('초안 6항목이 합계 100점으로 준비된다', function () {
    $this->seed(EvaluationItemSeeder::class);

    expect(EvaluationItem::sheet()->pluck('key')->all())->toBe([
        'health', 'experience', 'diligence', 'communication', 'family_ties', 'contract_understanding',
    ]);
    expect(EvaluationItem::totalMaxScore())->toBe(100);
});

it('항목을 추가하면 시트 만점이 올라간다', function () {
    $before = EvaluationItem::totalMaxScore();

    $this->actingAs($this->evalAdmin)
        ->postJson(route('admin.grid.evaluation-items.save'), [
            'added' => [[
                'key' => 'driving',
                'label' => '운전 가능 여부',
                'hint' => '트랙터·화물차 운전 경험',
                'max_score' => 10,
                'sort_order' => 7,
                'active' => 1,
            ]],
        ])
        ->assertOk()->assertJsonPath('ok', true);

    expect(EvaluationItem::totalMaxScore())->toBe($before + 10);
    expect(EvaluationItem::where('key', 'driving')->exists())->toBeTrue();
});

it('키가 중복되거나 형식이 어긋나면 저장되지 않는다', function () {
    $this->seed(EvaluationItemSeeder::class);

    // 이미 있는 key
    $this->actingAs($this->evalAdmin)
        ->postJson(route('admin.grid.evaluation-items.save'), [
            'added' => [['key' => 'health', 'label' => '중복', 'max_score' => 10]],
        ])
        ->assertStatus(422);

    // 대문자·공백은 점수 배열 키로 쓸 수 없다
    $this->actingAs($this->evalAdmin)
        ->postJson(route('admin.grid.evaluation-items.save'), [
            'added' => [['key' => 'Health Check', 'label' => '형식 오류', 'max_score' => 10]],
        ])
        ->assertStatus(422);

    expect(EvaluationItem::where('label', '중복')->exists())->toBeFalse();
});

it('배점 0 이하는 저장되지 않는다', function () {
    $this->actingAs($this->evalAdmin)
        ->postJson(route('admin.grid.evaluation-items.save'), [
            'added' => [['key' => 'zero_item', 'label' => '배점 없음', 'max_score' => 0]],
        ])
        ->assertStatus(422);

    expect(EvaluationItem::where('key', 'zero_item')->exists())->toBeFalse();
});

it('항목을 삭제해도 지난 평가 점수는 남는다', function () {
    $item = EvaluationItem::factory()->create(['key' => 'temp_item']);

    $evaluation = InterviewEvaluation::factory()->create([
        'scores' => ['temp_item' => 15],
    ]);

    $this->actingAs($this->evalAdmin)
        ->postJson(route('admin.grid.evaluation-items.save'), [
            'deleted' => [['id' => $item->id]],
        ])
        ->assertOk();

    expect(EvaluationItem::find($item->id))->toBeNull();
    // 당시 기준의 증빙이므로 지난 평가는 그대로여야 한다
    expect($evaluation->refresh()->scores)->toBe(['temp_item' => 15]);
});
