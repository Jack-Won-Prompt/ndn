<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Enums\FarmVisitStatus;
use App\Models\User;
use Database\Factories\FarmVisitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 본사 월별 농가 방문 점검 (CLAUDE.md §5, §7-2).
 *
 * 농가 상태·근로자 근무 현황·애로사항·조치사항을 기록한다. 위치정보는 저장하지 않으며
 * 방문 증빙은 현장 사진(FarmVisitPhoto, private 저장)으로 남긴다.
 *
 * @property FarmVisitStatus $farm_status
 * @property FarmVisitStatus $worker_status
 */
class FarmVisit extends Model
{
    /** @use HasFactory<FarmVisitFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'farm_id', 'visited_by', 'visited_on',
        'farm_status', 'worker_status', 'worker_headcount',
        'work_note', 'issue_note', 'action_note', 'memo',
    ];

    protected function casts(): array
    {
        return [
            'visited_on' => 'date',
            'farm_status' => FarmVisitStatus::class,
            'worker_status' => FarmVisitStatus::class,
            'worker_headcount' => 'integer',
        ];
    }

    /** @return BelongsTo<Farm, $this> */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /** @return BelongsTo<User, $this> */
    public function visitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visited_by');
    }

    /** @return HasMany<FarmVisitPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(FarmVisitPhoto::class)->orderBy('id');
    }

    /** 이 방문에서 진행한 근로자별 인터뷰 @return HasMany<MonthlyInterview, $this> */
    public function interviews(): HasMany
    {
        return $this->hasMany(MonthlyInterview::class, 'farm_visit_id')->orderBy('id');
    }
}
