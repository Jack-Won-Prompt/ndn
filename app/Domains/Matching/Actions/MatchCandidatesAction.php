<?php

declare(strict_types=1);

namespace App\Domains\Matching\Actions;

use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Shared\Enums\Gender;
use Illuminate\Support\Collection;

/**
 * 수요 조건에 맞는 근로자 추천 (업무흐름 §4 — 매칭 엔진).
 *
 * 조건을 "맞으면 통과"가 아니라 **항목별로 맞았는지 이유를 함께** 돌려준다.
 * 담당자가 왜 이 사람이 추천됐는지 보고 판단해야 하기 때문이다.
 *
 * 대조 방식이 조건마다 다르다:
 *   - 국적·성별  : 평문 컬럼 → SQL 로 후보를 좁힌다
 *   - 나이       : birth_date 가 encrypted 라 SQL 불가(§7-1) → 좁힌 뒤 PHP 로 계산
 *   - 형제 동반  : allow_siblings 가 false 면 그룹 배정을 막는 용도로만 쓴다
 *
 * 나이 정보가 없는 근로자는 제외하지 않고 '확인 필요'로 표시한다 — 자동으로
 * 걸러내면 담당자가 존재 자체를 모르게 되기 때문이다.
 */
class MatchCandidatesAction
{
    /** 한 번에 돌려줄 최대 후보 수 — 복호화 비용이 있어 상한을 둔다. */
    private const MAX_CANDIDATES = 100;

    /**
     * @return Collection<int, array{worker: Worker, score: int, matches: array<string, bool|null>}>
     *                                                                                               matches 의 null 은 '판단 불가(정보 없음)'
     */
    public function execute(DemandRequest $demand): Collection
    {
        $candidates = Worker::query()
            ->unassigned()
            ->where('status', WorkerStatus::Active->value)
            // 국적은 필수 조건이라 SQL 에서 바로 좁힌다
            ->where('nationality', $demand->nationality)
            ->orderBy('id')
            ->limit(self::MAX_CANDIDATES)
            ->get();

        return $candidates
            ->map(fn (Worker $worker) => $this->evaluate($worker, $demand))
            // 점수 높은 순, 같으면 먼저 등록된 순
            ->sortByDesc(fn (array $row) => [$row['score'], -$row['worker']->id])
            ->values();
    }

    /**
     * 근로자 1명을 수요 조건과 대조한다.
     *
     * @return array{worker: Worker, score: int, matches: array<string, bool|null>}
     */
    private function evaluate(Worker $worker, DemandRequest $demand): array
    {
        $matches = [
            'nationality' => true, // 쿼리에서 이미 일치시킴
            'gender' => $this->matchesGender($worker, $demand),
            'age' => $this->matchesAge($worker, $demand),
        ];

        // 확실히 맞은 항목만 점수로 센다. null(판단 불가)은 0점이되 제외하지는 않는다.
        $score = count(array_filter($matches, fn (?bool $m) => $m === true));

        return ['worker' => $worker, 'score' => $score, 'matches' => $matches];
    }

    /** 수요가 '무관'이면 항상 통과. 근로자 성별 정보가 없으면 판단 불가. */
    private function matchesGender(Worker $worker, DemandRequest $demand): ?bool
    {
        $wanted = $demand->gender;

        if ($wanted === null || $wanted === Gender::Any) {
            return true;
        }

        return $worker->gender === null ? null : $worker->gender === $wanted;
    }

    /** 나이 조건이 없으면 통과. 생년월일이 없으면 판단 불가. */
    private function matchesAge(Worker $worker, DemandRequest $demand): ?bool
    {
        if ($demand->age_min === null && $demand->age_max === null) {
            return true;
        }

        $age = $worker->age();

        if ($age === null) {
            return null;
        }

        if ($demand->age_min !== null && $age < $demand->age_min) {
            return false;
        }

        if ($demand->age_max !== null && $age > $demand->age_max) {
            return false;
        }

        return true;
    }
}
