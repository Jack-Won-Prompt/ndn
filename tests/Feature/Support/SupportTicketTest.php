<?php

declare(strict_types=1);

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Actions\CreateSupportTicketAction;
use App\Domains\Support\Actions\UpdateTicketStatusAction;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Enums\TicketType;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('민원을 접수하면 open 상태로 생성된다', function () {
    $ticket = app(CreateSupportTicketAction::class)->execute(
        Worker::factory()->create(), TicketType::ExtendStay, '계약 연장 문의'
    );

    expect($ticket->status)->toBe(TicketStatus::Open)
        ->and($ticket->type)->toBe(TicketType::ExtendStay);
});

it('상태 전이는 허용된 방향만 가능하다', function () {
    $ticket = SupportTicket::factory()->create(['status' => TicketStatus::Resolved]);

    app(UpdateTicketStatusAction::class)->execute($ticket, TicketStatus::Open);
})->throws(RuntimeException::class);

it('완료 처리 시 resolved_at 이 기록되고 담당자가 배정된다', function () {
    $admin = User::factory()->create();
    $ticket = SupportTicket::factory()->create(['status' => TicketStatus::Open]);

    app(UpdateTicketStatusAction::class)->execute($ticket, TicketStatus::Resolved, $admin);

    expect($ticket->fresh()->status)->toBe(TicketStatus::Resolved)
        ->and($ticket->fresh()->resolved_at)->not->toBeNull()
        ->and($ticket->fresh()->assigned_user_id)->toBe($admin->id);
});

it('근로자가 API 로 민원을 접수하고 본인 것만 조회한다', function () {
    $other = Worker::factory()->create();
    SupportTicket::factory()->create(['worker_id' => $other->id]);

    $me = Worker::factory()->create();
    Sanctum::actingAs($me);

    $this->postJson('/api/v1/tickets', ['type' => 'report', 'subject' => '숙소 문제'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'open');

    // 본인 민원만 (다른 근로자 것 제외)
    $this->getJson('/api/v1/tickets')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
