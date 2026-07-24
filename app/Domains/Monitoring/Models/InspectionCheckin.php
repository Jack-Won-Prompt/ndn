<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 점검자 방문 체크인 (CLAUDE.md §5, §7-2).
 * lat/lng 는 점검 증빙용으로만 존재한다.
 */
class InspectionCheckin extends Model
{
    protected $fillable = [
        'worker_id',
        'inspector_user_id',
        'lat',
        'lng',
        'checked_in_at',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'checked_in_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** @return BelongsTo<User, $this> */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }
}
