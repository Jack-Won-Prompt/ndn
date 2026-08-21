<?php

declare(strict_types=1);

namespace App\Domains\Demand\Actions;

use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Actions\ClosePlacementsAction;
use App\Domains\Matching\Models\Placement;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Monitoring\Models\WorkReview;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 농가 삭제 — 그 농가에 매인 것을 함께 정리한다.
 *
 * 농가·지자체는 기준정보다. 다른 화면은 전부 여기에 매달려 있으므로, 농가만
 * 지우고 나머지를 두면 아무 데도 속하지 않는 줄이 남는다. 실제로 운영에서
 * 그렇게 됐다 — 지운 농가 6곳에 배정 14건·수요 7건·방문점검 5건이 매달려 있었고,
 * 그중 12명은 **없는 농가에 배정된 상태**로 묶여 다른 농가에 넣을 수 없었다.
 *
 * 그래서 순서가 중요하다.
 *
 *   1) 살아 있는 배정을 **먼저 취소**한다 — 근로자를 미배정으로 풀어 주고
 *      사유를 감사 기록에 남기기 위해서다. 그냥 지우면 사람만 조용히 사라진다.
 *   2) 그다음 배정·입국·수요·방문점검·점검표를 접는다.
 *   3) 마지막에 농가를 접는다.
 *
 * 전부 soft delete 다. 잘못 지웠을 때 되돌릴 수 있어야 하고, 누가 어디에
 * 배정됐다가 어떻게 정리됐는지가 증빙으로 남아야 한다(업무흐름 §4).
 */
class DeleteFarmAction
{
    /**
     * @param  list<int>  $farmIds
     * @return array<string, int> 무엇을 얼마나 정리했는지 (화면에 그대로 알려 준다)
     */
    public function execute(array $farmIds, User $actor): array
    {
        $farmIds = array_values(array_unique(array_filter($farmIds)));

        if ($farmIds === []) {
            return [];
        }

        return DB::transaction(function () use ($farmIds, $actor) {
            $farms = Farm::whereIn('id', $farmIds)->get();

            if ($farms->isEmpty()) {
                return [];
            }

            $ids = $farms->pluck('id')->all();
            $names = $farms->pluck('name', 'id');

            // 배정을 접는 순서(취소 → 입국 기록 → 배정)는 한 군데서만 정한다.
            $summary = ['farms' => $farms->count()]
                + app(ClosePlacementsAction::class)->execute(
                    Placement::whereIn('farm_id', $ids)->get(),
                    $actor,
                    '농가 삭제: '.$names->values()->implode(', '),
                )
                + $this->closeDependents($ids);

            $farms->each->delete();

            activity('farm')
                ->causedBy($actor)
                ->withProperties(['farm_ids' => $ids] + $summary)
                ->log('농가 삭제(딸린 자료 함께 정리)');

            return array_filter($summary);
        });
    }

    /**
     * 배정 말고 농가에 매인 나머지 — 수요·방문 점검·점검표.
     *
     * @param  list<int>  $farmIds
     * @return array<string, int>
     */
    private function closeDependents(array $farmIds): array
    {
        return [
            'demands' => DemandRequest::whereIn('farm_id', $farmIds)->delete(),
            'visits' => FarmVisit::whereIn('farm_id', $farmIds)->delete(),
            'reviews' => WorkReview::whereIn('farm_id', $farmIds)->delete(),
        ];
    }

    /**
     * 정리 결과를 사람이 읽는 한 줄로.
     *
     * 몇 건이 함께 사라졌는지 그 자리에서 보여 주지 않으면, 농가 한 줄 지운 것이
     * 어디까지 번졌는지 알 길이 없다.
     *
     * @param  array<string, int>  $summary
     */
    public static function describe(array $summary): string
    {
        if (($summary['farms'] ?? 0) === 0) {
            return '';
        }

        $labels = [
            'cancelled' => '배정 취소',
            'placements' => '배정',
            'arrivals' => '입국 기록',
            'demands' => '수요',
            'visits' => '방문 점검',
            'reviews' => '점검표',
        ];

        $parts = [];
        foreach ($labels as $key => $label) {
            if (($summary[$key] ?? 0) > 0) {
                $parts[] = $label.' '.$summary[$key].'건';
            }
        }

        return "농가 {$summary['farms']}곳 삭제"
            .($parts === [] ? '' : ' (함께 정리: '.implode(', ', $parts).')');
    }
}
