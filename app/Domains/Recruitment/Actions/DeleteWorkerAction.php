<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Matching\Actions\ClosePlacementsAction;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Models\Worker;
use App\Models\User;
use App\Shared\Notifications\Models\DeviceToken;
use Illuminate\Support\Facades\DB;

/**
 * 근로자 삭제 — 그 사람에게 매인 것을 함께 정리한다.
 *
 * 농가와 같은 이유다(DeleteFarmAction). 근로자는 soft delete 인데 배정은 그
 * 사실을 모르므로, 사람만 지우면 **없는 사람이 배정된 자리**가 농가에 남는다.
 * 농가 정원은 계속 차 있고, 배정 현황에는 이름이 빈 줄이 보인다.
 *
 * 순서는 농가와 똑같다. 살아 있는 배정을 먼저 취소해 농가 자리를 비우고 사유를
 * 남긴 다음, 나머지를 접고, 마지막에 사람을 접는다.
 *
 * 딸린 자료 중 소프트 삭제를 쓰는 것(정착 신청·점검표)은 접고, 아닌 것은 지운다.
 * 지우는 쪽은 되돌릴 수 없으므로 지금 무엇이 그런지 여기 적어 둔다 —
 * 후보자·온보딩 제출·민원·긴급 SOS·본인 서류·동의 이력·기기 토큰.
 */
class DeleteWorkerAction
{
    /**
     * @param  list<int>  $workerIds
     * @return array<string, int> 무엇을 얼마나 정리했는지
     */
    public function execute(array $workerIds, User $actor): array
    {
        $workerIds = array_values(array_unique(array_filter($workerIds)));

        if ($workerIds === []) {
            return [];
        }

        return DB::transaction(function () use ($workerIds, $actor) {
            $workers = Worker::whereIn('id', $workerIds)->get();

            if ($workers->isEmpty()) {
                return [];
            }

            $ids = $workers->pluck('id')->all();

            // 배정을 접는 순서(취소 → 입국 기록 → 배정)는 한 군데서만 정한다.
            $summary = ['workers' => $workers->count()]
                + app(ClosePlacementsAction::class)->execute(
                    Placement::whereIn('worker_id', $ids)->get(),
                    $actor,
                    '근로자 삭제',
                )
                + $this->closeDependents($ids);

            $workers->each->delete();

            // 이름·여권번호는 절대 적지 않는다(§7-1·§7-3). 누가 몇 명을 지웠는지면 충분하다.
            activity('worker')
                ->causedBy($actor)
                ->withProperties(['worker_ids' => $ids] + $summary)
                ->log('근로자 삭제(딸린 자료 함께 정리)');

            return array_filter($summary);
        });
    }

    /**
     * 근로자에게 매인 나머지를 정리한다.
     *
     * 표 이름으로 직접 지운다. 모델을 하나씩 부르지 않는 이유는, 도메인이 늘 때마다
     * 여기 import 가 열 줄씩 늘고 정작 한 곳을 빠뜨리기 때문이다. 대신 어느 표를
     * 건드리는지 한눈에 보이게 적어 둔다.
     *
     * @param  list<int>  $ids
     * @return array<string, int>
     */
    private function closeDependents(array $ids): array
    {
        $out = [];

        // 소프트 삭제를 쓰는 표 — 접는다(되돌릴 수 있다).
        foreach (['settlement_requests' => 'settlements', 'work_reviews' => 'reviews'] as $table => $key) {
            $out[$key] = DB::table($table)
                ->whereIn('worker_id', $ids)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => now()]);
        }

        // 소프트 삭제가 없는 표 — 지운다.
        foreach ([
            'candidates' => 'candidates',
            'onboarding_submissions' => 'onboarding',
            'support_tickets' => 'tickets',
            'sos_alerts' => 'sos',
            'worker_files' => 'files',
            'consent_records' => 'consents',
        ] as $table => $key) {
            $out[$key] = DB::table($table)->whereIn('worker_id', $ids)->delete();
        }

        // 기기 토큰은 다형 관계라 타입까지 맞춰야 남의 토큰을 지우지 않는다.
        $out['devices'] = DeviceToken::query()
            ->where('tokenable_type', (new Worker)->getMorphClass())
            ->whereIn('tokenable_id', $ids)
            ->delete();

        return $out;
    }

    /**
     * 정리 결과를 사람이 읽는 한 줄로.
     *
     * @param  array<string, int>  $summary
     */
    public static function describe(array $summary): string
    {
        if (($summary['workers'] ?? 0) === 0) {
            return '';
        }

        $labels = [
            'cancelled' => '배정 취소',
            'placements' => '배정',
            'candidates' => '후보자',
            'onboarding' => '온보딩',
            'settlements' => '정착 신청',
            'tickets' => '민원',
            'sos' => '긴급 SOS',
            'files' => '본인 서류',
            'consents' => '동의 이력',
            'reviews' => '점검표',
            'devices' => '기기 등록',
        ];

        $parts = [];
        foreach ($labels as $key => $label) {
            if (($summary[$key] ?? 0) > 0) {
                $parts[] = $label.' '.$summary[$key].'건';
            }
        }

        return "근로자 {$summary['workers']}명 삭제"
            .($parts === [] ? '' : ' (함께 정리: '.implode(', ', $parts).')');
    }
}
