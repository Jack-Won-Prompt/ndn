<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Enums\WorkReviewResult;
use App\Domains\Monitoring\Enums\WorkReviewType;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Support\SignatureImage;
use Database\Factories\WorkReviewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 외국인근로자 근무상태 종합 점검표 한 건 (CLAUDE.md §5, 업무흐름 §7).
 *
 * 근로자 한 사람에 대한 점검 결과다. 농가 정보·근로자 인적사항은 여기에 담지
 * 않고 관계에서 이어 붙인다 — 여권번호 같은 암호화 필드를 복사해 두지 않기 위해서다.
 *
 * @property WorkReviewType $review_type
 * @property WorkReviewResult $result
 * @property RiskLevel $risk_level
 */
class WorkReview extends Model
{
    /** @use HasFactory<WorkReviewFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'worker_id', 'farm_id', 'inspector_user_id', 'farm_visit_id',
        'reviewed_at', 'place', 'review_type',
        'overtime_done', 'overtime_hours', 'overtime_consented',
        'avg_monthly_wage', 'last_paid_on', 'wage_unpaid',
        'board_provided', 'contract_followed', 'contract_violation',
        'result', 'notable', 'improvements', 'farm_requests',
        'action_due_on', 'action_assignee', 'recheck_on',
        'report_city', 'report_immigration', 'action_note',
        'signed_inspector', 'signed_farm', 'signed_worker', 'signed_interpreter',
        'signature_inspector', 'signature_farm', 'signature_worker', 'signature_interpreter',
        'risk_score', 'risk_level',
    ];

    /**
     * 서명란 (원본 §12). 순서는 원본과 같다.
     *
     * 키는 역할, 값은 [이름 컬럼, 서명 파일 컬럼, 화면 라벨].
     */
    public const SIGNATURE_ROLES = [
        'inspector' => ['signed_inspector', 'signature_inspector', '점검자'],
        'farm' => ['signed_farm', 'signature_farm', '농가 대표'],
        'worker' => ['signed_worker', 'signature_worker', '외국인근로자'],
        'interpreter' => ['signed_interpreter', 'signature_interpreter', '통역인'],
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'review_type' => WorkReviewType::class,
            'overtime_done' => 'boolean',
            'overtime_hours' => 'float',
            'overtime_consented' => 'boolean',
            // 개인 급여액 — 저장 시 암호화, 조회 시 복호화 (§7-1)
            'avg_monthly_wage' => 'encrypted',
            'last_paid_on' => 'date',
            'wage_unpaid' => 'boolean',
            'board_provided' => 'boolean',
            'contract_followed' => 'boolean',
            'result' => WorkReviewResult::class,
            'action_due_on' => 'date',
            'recheck_on' => 'date',
            'report_city' => 'boolean',
            'report_immigration' => 'boolean',
            'risk_score' => 'integer',
            'risk_level' => RiskLevel::class,
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
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_user_id');
    }

    /** @return BelongsTo<FarmVisit, $this> */
    public function farmVisit(): BelongsTo
    {
        return $this->belongsTo(FarmVisit::class, 'farm_visit_id');
    }

    /** @return HasMany<WorkReviewAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(WorkReviewAnswer::class);
    }

    /** 이 역할의 서명 파일 경로 (없으면 null). */
    public function signaturePath(string $role): ?string
    {
        $column = self::SIGNATURE_ROLES[$role][1] ?? null;

        return $column === null ? null : $this->{$column};
    }

    /** 서명 파일이 실제로 있는가. */
    public function hasSignature(string $role): bool
    {
        return SignatureImage::exists($this->signaturePath($role));
    }

    /** 서명이 하나라도 들어온 점검표인가 (목록에서 증빙 유무를 보여 준다). */
    public function signatureCount(): int
    {
        $n = 0;
        foreach (array_keys(self::SIGNATURE_ROLES) as $role) {
            if ($this->hasSignature($role)) {
                $n++;
            }
        }

        return $n;
    }
}
