<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Models\User;
use RuntimeException;

/**
 * 민원 상태 전이 + 담당자 배정 (업무흐름 §8).
 */
class UpdateTicketStatusAction
{
    public function execute(SupportTicket $ticket, TicketStatus $target, ?User $assignee = null): SupportTicket
    {
        if ($ticket->status !== $target && ! $ticket->status->canTransitionTo($target)) {
            throw new RuntimeException("전이할 수 없는 상태입니다: {$ticket->status->value} → {$target->value}");
        }

        $ticket->update([
            'status' => $target,
            'assigned_user_id' => $assignee?->id ?? $ticket->assigned_user_id,
            'resolved_at' => $target === TicketStatus::Resolved ? now() : null,
        ]);

        return $ticket;
    }
}
