<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Enums\TicketType;
use App\Models\User;
use Database\Factories\SupportTicketFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 민원 (CLAUDE.md §5, 업무흐름 §8).
 *
 * @property TicketType $type
 * @property TicketStatus $status
 */
class SupportTicket extends Model
{
    /** @use HasFactory<SupportTicketFactory> */
    use HasFactory;

    protected $fillable = [
        'worker_id',
        'type',
        'subject',
        'body',
        'status',
        'assigned_user_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => TicketType::class,
            'status' => TicketStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}
