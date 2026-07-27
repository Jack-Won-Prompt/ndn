<?php

declare(strict_types=1);

namespace App\Domains\Demand\Http\Controllers;

use App\Domains\Demand\Actions\CreateDemandRequestAction;
use App\Domains\Demand\Actions\SubmitDemandRequestAction;
use App\Domains\Demand\Http\Requests\StoreDemandRequestRequest;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Http\Controllers\Controller;
use App\Shared\Enums\Gender;
use App\Shared\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * 수요 신청 컨트롤러 (CLAUDE.md §4: 컨트롤러는 검증·인가·Action 호출·응답만).
 *
 * - 농가(farm_owner): 자기 농가의 수요만 열람·등록·제출
 * - NDN 관리자(본사): 여러 농가의 수요를 대신 등록(콘솔 그리드에서도 가능)
 */
class DemandRequestController extends Controller
{
    /** 목록 — 농가는 본인 농가 건만, 관리자·시청은 전체 (N+1 방지 즉시 로딩) */
    public function index(): View
    {
        $this->authorize('viewAny', DemandRequest::class);
        $user = Auth::user();

        $demands = DemandRequest::with(['farm', 'city'])
            ->when(
                $user->isRole(UserRole::FarmOwner) && ! $user->isRole(UserRole::NdnAdmin),
                fn ($q) => $q->whereIn('farm_id', $user->farms()->pluck('id')),
            )
            ->latest()
            ->paginate(20);

        return view('demand.index', ['demands' => $demands]);
    }

    /** 새 수요 신청 폼. 농가는 본인 농가만, 관리자는 전체 농가 선택. */
    public function create(): View
    {
        $this->authorize('create', DemandRequest::class);
        $user = Auth::user();

        $farms = $user->isRole(UserRole::NdnAdmin)
            ? Farm::orderBy('name')->get(['id', 'name', 'city_id'])
            : $user->farms()->orderBy('name')->get(['id', 'name', 'city_id']);

        return view('demand.create', [
            'farms' => $farms,
            'cities' => City::orderBy('name')->get(['id', 'name']),
            'genders' => Gender::cases(),
        ]);
    }

    public function store(
        StoreDemandRequestRequest $request,
        Farm $farm,
        CreateDemandRequestAction $action,
    ): RedirectResponse {
        $this->authorize('create', DemandRequest::class);

        // 농가는 본인 소유 농가로만 신청 가능 (관리자는 전체 허용)
        $user = Auth::user();
        abort_unless(
            $user->isRole(UserRole::NdnAdmin) || $farm->owner_user_id === $user->id,
            403,
        );

        $demand = $action->execute($farm, $request->validated());

        return redirect()
            ->route('demand.show', $demand)
            ->with('status', __('demand.created'));
    }

    public function submit(
        DemandRequest $demand,
        SubmitDemandRequestAction $action,
    ): RedirectResponse {
        $this->authorize('submit', $demand);

        $action->execute($demand);

        return redirect()
            ->route('demand.show', $demand)
            ->with('status', __('demand.submitted'));
    }

    public function show(DemandRequest $demand): View
    {
        $this->authorize('view', $demand);

        $demand->load(['farm', 'city']);

        return view('demand.show', ['demand' => $demand]);
    }
}
