<?php

declare(strict_types=1);

use App\Domains\Onboarding\Actions\GrantConsentAction;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Actions\AssignSettlementAction;
use App\Domains\Settlement\Actions\MoveSettlementStageAction;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Shared\Enums\ConsentPurpose;

/**
 * 정착 처리보드 (업무흐름 §6, CLAUDE.md §7-4).
 */
it('제3자 제공 동의가 있으면 대리점에 배정된다', function () {
    $worker = Worker::factory()->create();
    app(GrantConsentAction::class)->execute($worker, ConsentPurpose::ThirdPartyAgency, 'phone', agencyType: 'partner_agency');

    $req = SettlementRequest::factory()->create(['worker_id' => $worker->id]);

    app(AssignSettlementAction::class)->execute($req, 10);

    expect($req->fresh()->status)->toBe(SettlementStatus::Assigned)
        ->and($req->fresh()->assigned_agency_id)->toBe(10)
        ->and($req->fresh()->sla_due_at)->not->toBeNull();
});

it('동의가 없으면 배정이 거부된다 (§7-4)', function () {
    $worker = Worker::factory()->create(); // 동의 없음
    $req = SettlementRequest::factory()->create(['worker_id' => $worker->id]);

    app(AssignSettlementAction::class)->execute($req, 10);
})->throws(RuntimeException::class);

it('칸반 단계는 정해진 순서로만 이동한다', function () {
    $req = SettlementRequest::factory()->create(['status' => SettlementStatus::Assigned]);

    app(MoveSettlementStageAction::class)->execute($req, SettlementStatus::Processing);
    expect($req->fresh()->status)->toBe(SettlementStatus::Processing);

    // Processing → Done
    app(MoveSettlementStageAction::class)->execute($req->fresh(), SettlementStatus::Done);
    expect($req->fresh()->status)->toBe(SettlementStatus::Done)
        ->and($req->fresh()->completed_at)->not->toBeNull();
});

it('역방향 단계 이동은 거부된다', function () {
    $req = SettlementRequest::factory()->create(['status' => SettlementStatus::Done]);

    app(MoveSettlementStageAction::class)->execute($req, SettlementStatus::Received);
})->throws(RuntimeException::class);

it('SLA 기한을 넘긴 미완료 건은 지연으로 표시된다', function () {
    $req = SettlementRequest::factory()->create([
        'status' => SettlementStatus::Processing,
        'sla_due_at' => now()->subDay(),
    ]);

    expect($req->isOverdue())->toBeTrue();
});
