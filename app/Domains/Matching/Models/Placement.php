<?php

declare(strict_types=1);

namespace App\Domains\Matching\Models;

use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 매칭 확정 — 근로자 ↔ 농가 (CLAUDE.md §5).
 *
 * @property PlacementStatus $status
 */
class Placement extends Model
{
    // 농가가 지워지면 배정도 함께 접힌다. 완전히 지우지 않는 이유는
    // 누가 어디에 배정됐다가 어떻게 정리됐는지가 증빙으로 남아야 하기 때문이다(업무흐름 §4).
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'worker_id',
        'farm_id',
        'placement_group_id',
        'status',
        'start_date',
        'end_date',
        'confirmed_at',
        'confirmed_by',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => PlacementStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /** @return BelongsTo<User, $this> */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    /** 입국·이송 기록 (배정 확정 후 생성) @return HasOne<ArrivalRecord, $this> */
    public function arrival(): HasOne
    {
        return $this->hasOne(ArrivalRecord::class);
    }
}
