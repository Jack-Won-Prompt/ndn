<?php

declare(strict_types=1);

namespace App\Domains\Demand\Http\Controllers\Api;

use App\Domains\Demand\Models\City;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * 지원 가능한 지자체 목록 (근로자 앱 가입 화면의 지역 선택지).
 *
 * 가입은 토큰 발급 전에 이뤄지므로 인증 밖에 둔다. 지자체명·지역명은 공개 정보라
 * 개인정보가 섞이지 않는다(§7). 모집을 닫은 지역은 내려주지 않는다.
 */
class CityController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $cities = City::query()
            ->where('recruiting', true)
            ->withCount('workers')
            ->orderBy('region')
            ->orderBy('name')
            ->get(['id', 'name', 'region', 'quota', 'recruiting'])
            // 정원이 찬 지역은 고를 수 없으므로 목록에서 뺀다(§지역별 모집).
            ->filter(fn (City $c) => $c->quota === null || $c->workers_count < $c->quota)
            ->values();

        return response()->json([
            'data' => $cities->map(fn (City $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'region' => $c->region,
                // 앱 목록에 그대로 쓸 표시명 — "충청남도 당진시"
                'label' => $c->label(),
                // 남은 자리 (정원 미설정이면 null — 제한 없음)
                'remaining' => $c->quota === null ? null : max($c->quota - $c->workers_count, 0),
            ])->all(),
            'meta' => ['count' => $cities->count()],
        ]);
    }
}
