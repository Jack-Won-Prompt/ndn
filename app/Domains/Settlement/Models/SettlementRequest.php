<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Models;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Enums\SettlementType;
use App\Domains\Settlement\Models\Scopes\PartnerAgencyScope;
use Database\Factories\SettlementRequestFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 정착 서비스 신청 (CLAUDE.md §5).
 *
 * @property SettlementType $type
 */
#[ScopedBy(PartnerAgencyScope::class)]
class SettlementRequest extends Model
{
    /** @use HasFactory<SettlementRequestFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'worker_id',
        'type',
        'assigned_agency_id',
        'assigned_at',
        'status',
        'sla_due_at',
        'completed_at',
        'partner_note',
    ];

    protected function casts(): array
    {
        return [
            'type' => SettlementType::class,
            'status' => SettlementStatus::class,
            'assigned_at' => 'datetime',
            'sla_due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** @return HasMany<SettlementDocument, $this> 처리 증빙 문서 */
    public function documents(): HasMany
    {
        return $this->hasMany(SettlementDocument::class)->latest('id');
    }

    /** SLA 기한을 넘긴 미완료 건인지 */
    public function isOverdue(): bool
    {
        return $this->sla_due_at !== null
            && $this->status !== SettlementStatus::Done
            && $this->sla_due_at->isPast();
    }
}
