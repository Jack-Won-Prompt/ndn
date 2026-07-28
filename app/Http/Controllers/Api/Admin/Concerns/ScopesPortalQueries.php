<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Concerns;

use App\Models\User;
use App\Shared\Support\PortalScope;
use Illuminate\Http\Request;

/**
 * 관리자 API 컨트롤러 공통 — 역할 스코프·권한 확인·감사 로그.
 *
 * 상태를 바꾸는 엔드포인트는 반드시 authorizeDecision() 을 먼저 부른다.
 * 승인·검수 같은 판단은 NDN 관리자만 하고, 시청·농가는 조회만 한다.
 */
trait ScopesPortalQueries
{
    protected function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }

    /** 상태 변경 권한 확인 — 없으면 403. */
    protected function authorizeDecision(Request $request): User
    {
        $user = $this->actor($request);

        abort_unless(
            PortalScope::canDecide($user),
            403,
            '상태를 변경할 권한이 없습니다. 조회만 가능합니다.',
        );

        return $user;
    }

    /**
     * 근로자 개인정보 열람 기록 (CLAUDE.md §7-6).
     *
     * 관리자 화면·API 가 근로자 개인정보를 읽으면 누가·언제·어떤 worker_id 를
     * 봤는지 남긴다. 목록에서 이름이 보이는 것도 열람이므로 상세뿐 아니라
     * 목록 조회에도 남긴다.
     *
     * @param  list<int>  $workerIds
     */
    protected function logWorkerAccess(User $actor, array $workerIds, string $context): void
    {
        if ($workerIds === []) {
            return;
        }

        activity('personal-data')
            ->causedBy($actor)
            ->withProperties([
                'worker_ids' => array_values(array_unique($workerIds)),
                'context' => $context,
                'count' => count($workerIds),
            ])
            ->log('근로자 개인정보 열람(관리자 앱)');
    }

    /** 목록 페이지 크기 — 과도한 조회를 막는다. */
    protected function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 30), 1), 100);
    }

    /**
     * 상태별 건수 — 목록 화면 상단 요약 띠에 쓴다.
     *
     * **필터가 걸리기 전의** 스코프 쿼리를 넘겨야 한다. '대기 3건' 을 보려고
     * 필터를 걸었더니 그 숫자까지 3으로 바뀌면 요약이 쓸모없기 때문이다.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $scoped
     * @return array<string, int>
     */
    protected function statusCounts($scoped): array
    {
        return $scoped
            ->reorder()
            ->select('status')
            ->selectRaw('count(*) as aggregate_count')
            ->groupBy('status')
            ->pluck('aggregate_count', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();
    }
}
