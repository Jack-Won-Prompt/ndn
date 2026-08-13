<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Enums\WorkerExitReason;
use App\Domains\Support\Enums\WorkerExitStatus;
use App\Domains\Support\Enums\WorkerExitType;
use App\Models\User;
use Database\Factories\WorkerExitFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 근로자가 계약을 채우지 못하고 빠진 사건 한 건 (조기 귀국 또는 이탈).
 *
 * @property WorkerExitType $type
 * @property WorkerExitStatus $status
 * @property WorkerExitReason $reason
 */
class WorkerExit extends Model
{
    /** @use HasFactory<WorkerExitFactory> */
    use HasFactory;

    protected $fillable = [
        'worker_id', 'placement_id', 'support_ticket_id',
        'type', 'status', 'reason', 'reason_detail',
        'occurred_on', 'noticed_on', 'decided_at', 'decided_by',
        'departed_on', 'reported', 'reported_on', 'report_ref',
        'note', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkerExitType::class,
            'status' => WorkerExitStatus::class,
            'reason' => WorkerExitReason::class,
            'occurred_on' => 'date',
            'noticed_on' => 'date',
            'departed_on' => 'date',
            'reported_on' => 'date',
            'decided_at' => 'datetime',
            'reported' => 'boolean',
        ];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** @return BelongsTo<Placement, $this> */
    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    /** @return BelongsTo<SupportTicket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** 담당자가 아직 손대야 하는 건. */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', collect(WorkerExitStatus::cases())
            ->filter(fn (WorkerExitStatus $s) => $s->isOpen())
            ->map(fn (WorkerExitStatus $s) => $s->value)
            ->all());
    }

    /**
     * 연락이 끊긴 지 며칠인가 (이탈 건만).
     *
     * 이 숫자가 이탈 관리의 전부다. 위치를 쫓는 대신 '얼마나 오래 끊겼나'로
     * 급한 정도를 판단한다(§7-2).
     */
    public function daysUnreachable(): ?int
    {
        if ($this->type !== WorkerExitType::Absconded || $this->occurred_on === null) {
            return null;
        }

        // 종결된 건은 종결 시점까지만 센다. 지난 건이 매일 늘어나면 목록이 거짓말을 한다.
        $until = $this->status->isOpen()
            ? now()->startOfDay()
            : ($this->decided_at?->startOfDay() ?? $this->updated_at?->startOfDay() ?? now()->startOfDay());

        // Carbon 3 의 diffInDays 는 float 을 돌려준다. 화면에 '10.0일' 이 뜨지 않게 자른다.
        return max(0, (int) $this->occurred_on->startOfDay()->diffInDays($until, absolute: true));
    }

    /** 다음으로 누를 수 있는 버튼들. */
    public function nextStatuses(): array
    {
        return $this->status->allowedTransitions($this->type);
    }
}
