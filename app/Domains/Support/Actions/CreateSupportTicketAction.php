<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Enums\TicketType;
use App\Domains\Support\Models\SupportTicket;

/**
 * 민원 접수 (업무흐름 §8). 근로자 본인이 발신한다.
 */
class CreateSupportTicketAction
{
    public function execute(Worker $worker, TicketType $type, string $subject, ?string $body = null): SupportTicket
    {
        return SupportTicket::create([
            'worker_id' => $worker->id,
            'type' => $type,
            'subject' => $subject,
            'body' => $body,
            'status' => TicketStatus::Open,
        ]);
    }
}
