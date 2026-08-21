<?php

declare(strict_types=1);

namespace App\Domains\Demand\Actions;

use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Recruitment\Models\Candidate;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 수요 삭제.
 *
 * 배정은 함께 지우지 않는다. 배정은 농가에 매여 있지 수요에 매여 있지 않고
 * (placements 에 demand_id 가 없다), 수요는 '이만큼 필요하다' 는 신청서일 뿐이다.
 * 잘못 적은 신청서를 지웠다고 이미 그 농가에서 일하는 사람이 사라지면 안 된다.
 *
 * 대신 그 수요를 보고 뽑던 후보자는 가리키던 곳을 잃는다. 후보자 화면에서
 * '어느 수요 건인지' 가 빈칸이 되므로, 지우기 전에 연결을 끊어 둔다 —
 * DB 의 nullOnDelete 는 행이 실제로 지워질 때만 도는데 수요는 soft delete 라
 * 돌지 않는다. 그대로 두면 없는 수요를 가리킨 채 남는다.
 */
class DeleteDemandRequestAction
{
    /**
     * @param  list<int>  $demandIds
     * @return array<string, int> demands·candidates
     */
    public function execute(array $demandIds, User $actor): array
    {
        $demandIds = array_values(array_unique(array_filter($demandIds)));

        if ($demandIds === []) {
            return [];
        }

        return DB::transaction(function () use ($demandIds, $actor) {
            $demands = DemandRequest::whereIn('id', $demandIds)->get();

            if ($demands->isEmpty()) {
                return [];
            }

            $ids = $demands->pluck('id')->all();

            $summary = [
                'demands' => $demands->count(),
                'candidates' => Candidate::whereIn('demand_request_id', $ids)
                    ->update(['demand_request_id' => null]),
            ];

            DemandRequest::whereIn('id', $ids)->delete();

            activity('demand')
                ->causedBy($actor)
                ->withProperties(['demand_ids' => $ids] + $summary)
                ->log('수요 삭제');

            return $summary;
        });
    }

    /**
     * 정리 결과를 사람이 읽는 한 줄로.
     *
     * @param  array<string, int>  $summary
     */
    public static function describe(array $summary): string
    {
        if (($summary['demands'] ?? 0) === 0) {
            return '';
        }

        $tail = ($summary['candidates'] ?? 0) > 0
            ? " (후보자 {$summary['candidates']}건의 연결을 끊었습니다)"
            : '';

        return "수요 {$summary['demands']}건을 삭제했습니다.{$tail}"
            .' 배정은 농가에 매여 있어 그대로 남습니다.';
    }
}
