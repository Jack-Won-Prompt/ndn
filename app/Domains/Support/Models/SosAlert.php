<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\SosStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 긴급 SOS (CLAUDE.md §5, §7-2).
 * lat/lng 는 SOS 발신 순간 1회 좌표.
 *
 * @property SosStatus $status
 */
class SosAlert extends Model
{
    protected $fillable = [
        'worker_id',
        'lat',
        'lng',
        'alerted_at',
        'status',
        'acknowledged_at',
        'acknowledged_by',
        'closed_at',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'alerted_at' => 'datetime',
            'status' => SosStatus::class,
            'acknowledged_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** 확인한 담당자 @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** 접수 후 확인까지 걸린 시간(분). 미확인이면 지금까지 경과한 시간. */
    public function responseMinutes(): int
    {
        $until = $this->acknowledged_at ?? now();

        return (int) $this->alerted_at->diffInMinutes($until);
    }
}
