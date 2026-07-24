<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Models;

use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Recruitment\Enums\CandidateStatus;
use Database\Factories\CandidateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 후보자 (CLAUDE.md §5, 업무흐름 §2).
 *
 * @property CandidateStatus $status
 */
class Candidate extends Model
{
    /** @use HasFactory<CandidateFactory> */
    use HasFactory;

    protected $fillable = [
        'demand_request_id',
        'worker_id',
        'name',
        'nationality',
        'age',
        'gender',
        'status',
        'queue_position',
    ];

    protected function casts(): array
    {
        return [
            'status' => CandidateStatus::class,
            'age' => 'integer',
        ];
    }

    /** @return BelongsTo<DemandRequest, $this> */
    public function demandRequest(): BelongsTo
    {
        return $this->belongsTo(DemandRequest::class);
    }

    /** @return HasMany<InterviewEvaluation, $this> */
    public function evaluations(): HasMany
    {
        return $this->hasMany(InterviewEvaluation::class);
    }

    /**
     * 보류 대기열(순번 오름차순).
     *
     * @param  Builder<Candidate>  $query
     */
    public function scopeWaitlist(Builder $query): void
    {
        $query->where('status', CandidateStatus::Held->value)
            ->whereNotNull('queue_position')
            ->orderBy('queue_position');
    }
}
