<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Monitoring\Models\WorkReview;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 이미 지워진 농가에 매달려 남은 자료를 정리한다.
 *
 * 예전에는 농가를 지워도 배정·수요·방문점검이 그대로 남았다. DB 의 cascadeOnDelete
 * 는 행이 실제로 지워질 때만 도는데 농가는 soft delete 라 돌지 않았기 때문이다.
 * 지금은 DeleteFarmAction 이 함께 정리하지만, 그 전에 쌓인 것은 남아 있다.
 *
 * 남아 있으면 두 가지가 어긋난다.
 *   - 배정 현황·지역별 배치·근로자 앱에 **없는 농가**에 매인 줄이 계속 보인다
 *   - 그 근로자는 '이미 배정됨' 으로 잡혀 다른 농가에 넣을 수 없다
 *
 * 기본은 **보여 주기만** 한다(--apply 를 붙여야 실제로 정리). 운영 데이터를
 * 건드리는 명령이라 실수로 도는 일이 없어야 한다(CLAUDE.md §11).
 */
class SweepOrphanedFarmData extends Command
{
    protected $signature = 'farms:sweep-orphans
        {--apply : 실제로 정리한다 (없으면 무엇을 정리할지 보여 주기만 한다)}';

    protected $description = '이미 지워진 농가에 남아 있는 배정·수요·방문점검·점검표를 정리';

    public function handle(): int
    {
        $farms = Farm::onlyTrashed()->pluck('name', 'id');

        if ($farms->isEmpty()) {
            $this->info('지워진 농가가 없습니다.');

            return self::SUCCESS;
        }

        $ids = $farms->keys()->all();
        $found = $this->survey($ids);

        $this->line('지워진 농가 '.$farms->count().'곳에 남아 있는 자료');
        foreach ($found as $label => $n) {
            $this->line(sprintf('  %-14s %s건', $label, $n));
        }

        if (array_sum($found) === 0) {
            $this->info('정리할 것이 없습니다.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('보여 주기만 했습니다. 실제로 정리하려면 --apply 를 붙이세요.');

            return self::SUCCESS;
        }

        $this->sweep($ids, $farms);
        $this->newLine();
        $this->info('정리했습니다. 남은 고아: '.array_sum($this->survey($ids)).'건');

        return self::SUCCESS;
    }

    /**
     * 무엇이 얼마나 남아 있는지.
     *
     * @param  list<int>  $ids
     * @return array<string, int>
     */
    private function survey(array $ids): array
    {
        $placementIds = Placement::withTrashed()->whereIn('farm_id', $ids)->pluck('id');

        return [
            '배정' => Placement::whereIn('farm_id', $ids)->count(),
            '  └ 살아있는 것' => Placement::whereIn('farm_id', $ids)
                ->whereIn('status', [PlacementStatus::Proposed->value, PlacementStatus::Confirmed->value])
                ->count(),
            '입국 기록' => $placementIds->isEmpty()
                ? 0
                : ArrivalRecord::whereIn('placement_id', $placementIds)->count(),
            '수요' => DemandRequest::whereIn('farm_id', $ids)->count(),
            '방문 점검' => FarmVisit::whereIn('farm_id', $ids)->count(),
            '점검표' => WorkReview::whereIn('farm_id', $ids)->count(),
        ];
    }

    /**
     * 정리한다.
     *
     * 살아 있는 배정은 먼저 '취소' 로 바꾼다 — 근로자를 미배정으로 풀어 주기
     * 위해서이고, 그냥 접어 버리면 왜 빠졌는지가 남지 않기 때문이다.
     *
     * @param  list<int>  $ids
     * @param  Collection<int, string>  $farms
     */
    private function sweep(array $ids, $farms): void
    {
        DB::transaction(function () use ($ids, $farms) {
            $live = Placement::whereIn('farm_id', $ids)
                ->whereIn('status', [PlacementStatus::Proposed->value, PlacementStatus::Confirmed->value])
                ->get();

            foreach ($live as $placement) {
                $placement->forceFill([
                    'status' => PlacementStatus::Cancelled,
                    'note' => '농가 삭제: '.($farms[$placement->farm_id] ?? '이름 없음'),
                ])->save();
            }

            $placementIds = Placement::whereIn('farm_id', $ids)->pluck('id');
            if ($placementIds->isNotEmpty()) {
                ArrivalRecord::whereIn('placement_id', $placementIds)->delete();
            }

            Placement::whereIn('farm_id', $ids)->delete();
            DemandRequest::whereIn('farm_id', $ids)->delete();
            FarmVisit::whereIn('farm_id', $ids)->delete();
            WorkReview::whereIn('farm_id', $ids)->delete();

            // 운영 데이터를 고친 기록을 남긴다 (CLAUDE.md §11).
            activity('farm')
                ->withProperties([
                    'farm_ids' => $ids,
                    'cancelled' => $live->count(),
                    'command' => 'farms:sweep-orphans',
                ])
                ->log('지워진 농가에 남아 있던 자료 정리');
        });
    }
}
