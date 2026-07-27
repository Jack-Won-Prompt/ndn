<?php

declare(strict_types=1);

namespace App\Domains\Matching\Actions;

use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 배정 생성 (업무흐름 §4).
 *
 * 여러 명을 한 번에 배정할 수 있고, 형제·가족 동반이면 placement_group_id 를
 * 공유시켜 이후 단계에서 함께 움직이게 한다(CLAUDE.md §5).
 *
 * 생성 시점은 `proposed`(제안)다. 확정은 ConfirmPlacementAction 이 따로 하며,
 * 확정 시 입국 기록이 만들어진다.
 */
class CreatePlacementAction
{
    /**
     * @param  list<int>  $workerIds
     * @return Collection<int, Placement>
     *
     * @throws RuntimeException 이미 배정됐거나·비활성 근로자·정원 초과일 때
     */
    public function execute(
        DemandRequest $demand,
        array $workerIds,
        User $actor,
        bool $asGroup = false,
    ): Collection {
        if ($workerIds === []) {
            throw new RuntimeException('배정할 근로자를 선택해 주세요.');
        }

        // 형제·가족 동반을 허용하지 않은 수요에는 그룹 배정을 만들지 않는다.
        if ($asGroup && ! $demand->allow_siblings) {
            throw new RuntimeException('이 수요는 형제·가족 동반을 허용하지 않습니다.');
        }

        return DB::transaction(function () use ($demand, $workerIds, $actor, $asGroup) {
            $workers = Worker::query()->whereIn('id', $workerIds)->lockForUpdate()->get();

            if ($workers->count() !== count($workerIds)) {
                throw new RuntimeException('존재하지 않는 근로자가 포함돼 있습니다.');
            }

            foreach ($workers as $worker) {
                if ($worker->status !== WorkerStatus::Active) {
                    throw new RuntimeException("{$worker->name} 님은 재직 상태가 아닙니다.");
                }

                // 트랜잭션 안에서 다시 확인한다 — 목록을 본 뒤 다른 담당자가
                // 먼저 배정했을 수 있다.
                $already = Placement::where('worker_id', $worker->id)
                    ->whereIn('status', [
                        PlacementStatus::Proposed->value,
                        PlacementStatus::Confirmed->value,
                    ])
                    ->exists();

                if ($already) {
                    throw new RuntimeException("{$worker->name} 님은 이미 배정돼 있습니다.");
                }
            }

            $this->assertCapacity($demand, count($workerIds));

            $groupId = $asGroup ? (string) Str::uuid() : null;

            $created = $workers->map(fn (Worker $worker) => Placement::create([
                'worker_id' => $worker->id,
                'farm_id' => $demand->farm_id,
                'placement_group_id' => $groupId,
                'status' => PlacementStatus::Proposed,
                'start_date' => $demand->period_start,
                'end_date' => $demand->period_end,
            ]));

            activity('placement')
                ->causedBy($actor)
                ->withProperties([
                    'demand_id' => $demand->id,
                    'farm_id' => $demand->farm_id,
                    'worker_ids' => $workerIds,
                    'group' => $groupId,
                ])
                ->log('배정 생성');

            return $created;
        });
    }

    /** 요청 인원을 넘겨 배정하지 못하게 막는다. */
    private function assertCapacity(DemandRequest $demand, int $adding): void
    {
        $current = Placement::where('farm_id', $demand->farm_id)
            ->whereIn('status', [
                PlacementStatus::Proposed->value,
                PlacementStatus::Confirmed->value,
            ])
            ->count();

        if ($current + $adding > $demand->headcount) {
            $remaining = max($demand->headcount - $current, 0);

            throw new RuntimeException(
                "요청 인원({$demand->headcount}명)을 초과합니다. 남은 자리: {$remaining}명"
            );
        }
    }
}
