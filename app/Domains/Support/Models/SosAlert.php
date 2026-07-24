<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Recruitment\Models\Worker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 긴급 SOS (CLAUDE.md §5, §7-2).
 * lat/lng 는 SOS 발신 순간 1회 좌표.
 */
class SosAlert extends Model
{
    protected $fillable = [
        'worker_id',
        'lat',
        'lng',
        'alerted_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'alerted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }
}
