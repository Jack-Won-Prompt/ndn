<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use Database\Factories\WorkReviewAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 점검표 한 항목에 대한 응답.
 */
class WorkReviewAnswer extends Model
{
    /** @use HasFactory<WorkReviewAnswerFactory> */
    use HasFactory;

    protected $fillable = ['work_review_id', 'work_review_item_id', 'value', 'note'];

    /** @return BelongsTo<WorkReview, $this> */
    public function review(): BelongsTo
    {
        return $this->belongsTo(WorkReview::class);
    }

    /** @return BelongsTo<WorkReviewItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(WorkReviewItem::class, 'work_review_item_id');
    }
}
