<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Models;

use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use Database\Factories\OnboardingSubmissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 셀프 온보딩 제출물 (CLAUDE.md §5, §7).
 *
 * payload 는 본인 기입 개인정보라 encrypted:array 로 저장한다(§7-1).
 *
 * @property OnboardingStatus $status
 * @property array<string, mixed>|null $payload
 */
class OnboardingSubmission extends Model
{
    /** @use HasFactory<OnboardingSubmissionFactory> */
    use HasFactory;

    protected $fillable = [
        'worker_id',
        'payload',
        'signature_path',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OnboardingStatus::class,
            'payload' => 'encrypted:array',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
