<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use Database\Factories\MonthlyInterviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 월별 인터뷰 (CLAUDE.md §5, 업무흐름 §7).
 *
 * @property RiskLevel $risk_level
 */
class MonthlyInterview extends Model
{
    /** @use HasFactory<MonthlyInterviewFactory> */
    use HasFactory;

    /** 6개 점검 항목의 속성명 (true = 양호) */
    public const ITEMS = [
        'pay_received',
        'no_discrimination',
        'follows_rules',
        'adapts_group',
        'health_ok',
        'no_flight_signs',
    ];

    protected $fillable = [
        'worker_id',
        'inspector_user_id',
        'inspection_checkin_id',
        'interviewed_on',
        'pay_received',
        'no_discrimination',
        'follows_rules',
        'adapts_group',
        'health_ok',
        'no_flight_signs',
        'risk_score',
        'risk_level',
        'memo',
    ];

    protected function casts(): array
    {
        return [
            'interviewed_on' => 'date',
            'pay_received' => 'boolean',
            'no_discrimination' => 'boolean',
            'follows_rules' => 'boolean',
            'adapts_group' => 'boolean',
            'health_ok' => 'boolean',
            'no_flight_signs' => 'boolean',
            'risk_score' => 'integer',
            'risk_level' => RiskLevel::class,
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

    /** @return BelongsTo<InspectionCheckin, $this> */
    public function checkin(): BelongsTo
    {
        return $this->belongsTo(InspectionCheckin::class, 'inspection_checkin_id');
    }
}
