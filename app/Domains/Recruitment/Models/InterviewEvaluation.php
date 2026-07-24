<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Models;

use App\Models\User;
use Database\Factories\InterviewEvaluationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 면접 평가 (CLAUDE.md §5, 업무흐름 §2).
 */
class InterviewEvaluation extends Model
{
    /** @use HasFactory<InterviewEvaluationFactory> */
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'interviewer_user_id',
        'scores',
        'total_score',
        'result',
        'comment',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'total_score' => 'integer',
            'evaluated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Candidate, $this> */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /** @return BelongsTo<User, $this> */
    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_user_id');
    }
}
