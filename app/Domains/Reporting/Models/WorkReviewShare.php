<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Models;

use App\Domains\Monitoring\Models\WorkReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 점검표를 관계기관에 보낸 기록 한 줄 (발송 × 점검표 × 수신처).
 *
 * 개인정보 제3자 제공 이력이라 지우지 않는다. 점검표가 지워지면 함께 사라진다.
 */
class WorkReviewShare extends Model
{
    protected $fillable = [
        'batch_id', 'work_review_id', 'recipient_email', 'recipient_org',
        'note', 'sent_by', 'sent_at',
    ];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    /** @return BelongsTo<WorkReview, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(WorkReview::class, 'work_review_id');
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
