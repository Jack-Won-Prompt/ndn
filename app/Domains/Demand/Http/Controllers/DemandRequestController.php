<?php

declare(strict_types=1);

namespace App\Domains\Demand\Http\Controllers;

use App\Domains\Demand\Actions\CreateDemandRequestAction;
use App\Domains\Demand\Actions\SubmitDemandRequestAction;
use App\Domains\Demand\Http\Requests\StoreDemandRequestRequest;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 수요 신청 컨트롤러 (CLAUDE.md §4: 컨트롤러는 검증·인가·Action 호출·응답만).
 */
class DemandRequestController extends Controller
{
    /** 목록 — N+1 방지를 위해 farm/city 즉시 로딩 (§11) */
    public function index(): View
    {
        $this->authorize('viewAny', DemandRequest::class);

        $demands = DemandRequest::with(['farm', 'city'])
            ->latest()
            ->paginate(20);

        return view('demand.index', ['demands' => $demands]);
    }

    public function store(
        StoreDemandRequestRequest $request,
        Farm $farm,
        CreateDemandRequestAction $action,
    ): RedirectResponse {
        $this->authorize('create', DemandRequest::class);

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
