<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * 지역(시군)별 모집·배치 현황 — "당진시 배치 인원 및 농가" 를 지역 단위로 갈라 본다.
 *
 * 지원자 수는 workers.city_id(가입 시 고른 지역) 기준,
 * 배치 인원은 placements → farms.city_id(실제 배치된 농가의 지역) 기준이다.
 * 두 값이 다를 수 있으므로 따로 센다.
 */
class RegionController extends Controller
{
    /**
     * 지역별 요약 — 정원·지원자·배치 인원·농가 수.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(): array
    {
        // 지역별 농가 수
        $farmCounts = Farm::query()
            ->whereNotNull('city_id')
            ->groupBy('city_id')
            ->pluck(DB::raw('count(*)'), 'city_id');

        // 지역별 확정 배정 인원 (농가의 지역 기준)
        $placed = Placement::query()
            ->join('farms', 'farms.id', '=', 'placements.farm_id')
            ->where('placements.status', PlacementStatus::Confirmed->value)
            ->whereNotNull('farms.city_id')
            ->groupBy('farms.city_id')
            ->pluck(DB::raw('count(*)'), 'farms.city_id');

        return City::query()
            ->withCount([
                'workers',
                'workers as active_workers_count' => fn ($q) => $q->where('status', WorkerStatus::Active->value),
                'workers as pending_workers_count' => fn ($q) => $q->where('status', WorkerStatus::Pending->value),
            ])
            ->orderBy('region')->orderBy('name')
            ->get()
            ->map(function (City $c) use ($farmCounts, $placed) {
                $applicants = $c->workers_count;
                $quota = $c->quota;

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'region' => $c->region,
                    'label' => $c->label(),
                    'quota' => $quota,
                    'recruiting' => $c->recruiting,
                    'applicants' => $applicants,
                    'active' => $c->active_workers_count,
                    'pending' => $c->pending_workers_count,
                    'placed' => (int) ($placed[$c->id] ?? 0),
                    'farms' => (int) ($farmCounts[$c->id] ?? 0),
                    // 남은 정원 — 미설정이면 null(제한 없음)
                    'remaining' => $quota === null ? null : max($quota - $applicants, 0),
                    'open' => $c->isOpenForSignup(),
                ];
            })->all();
    }

    /** 한 지역의 농가별 배치 현황 (드릴다운). */
    public function show(City $city): JsonResponse
    {
        $farms = Farm::query()
            ->where('city_id', $city->id)
            ->withCount([
                'placements as placed_count' => fn ($q) => $q->where('status', PlacementStatus::Confirmed->value),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'main_crop', 'address']);

        return response()->json([
            'id' => $city->id,
            'label' => $city->label(),
            'quota' => $city->quota,
            'recruiting' => $city->recruiting,
            'applicants' => $city->workers()->count(),
            'farms' => $farms->map(fn (Farm $f) => [
                'id' => $f->id,
                'name' => $f->name,
                'main_crop' => $f->main_crop,
                'address' => $f->address,
                'placed' => $f->placed_count,
            ])->all(),
        ]);
    }
}
