<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Recruitment\Enums\CandidateStatus;
use App\Domains\Recruitment\Models\Candidate;
use App\Domains\Recruitment\Models\InterviewEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InterviewEvaluation>
 */
class InterviewEvaluationFactory extends Factory
{
    protected $model = InterviewEvaluation::class;

    public function definition(): array
    {
        return [
            'candidate_id' => Candidate::factory(),
            'interviewer_user_id' => null,
            'scores' => ['korean' => 25, 'attitude' => 30, 'health' => 25],
            'total_score' => 80,
            'result' => CandidateStatus::Passed->value,
            'comment' => null,
            'evaluated_at' => now(),
        ];
    }
}
