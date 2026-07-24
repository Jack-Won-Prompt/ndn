<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\SosAlert;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * NDN 운영 콘솔 — MDI 탭 워크스페이스 셸 + 임베디드 업무 화면.
 *
 * 셸(shell)은 사이드바 + 탭바 + iframe 컨테이너만 렌더한다. 메뉴 클릭 시 JS 가
 * /admin/screen/{key} 를 iframe 탭으로 열어 화면 전환 없이 여러 화면을 탭으로 유지한다.
 */
class ConsoleController extends Controller
{
    /**
     * 사이드바 메뉴 정의. 각 항목이 하나의 업무 화면(탭)이 된다.
     *
     * @return array<int, array{group: string, items: array<int, array{key:string,label:string,icon:string}>}>
     */
    public static function menu(): array
    {
        return [
            [
                'group' => '',
                'items' => [
                    ['key' => 'dashboard', 'label' => '대시보드', 'icon' => 'grid'],
                ],
            ],
            [
                'group' => '수요·모집',
                'items' => [
                    ['key' => 'demand', 'label' => '수요 신청', 'icon' => 'clipboard'],
                ],
            ],
            [
                'group' => '근로자',
                'items' => [
                    ['key' => 'workers', 'label' => '근로자', 'icon' => 'users'],
                    ['key' => 'onboarding', 'label' => '온보딩 검수', 'icon' => 'inbox'],
                ],
            ],
        ];
    }

    /** 탭 워크스페이스 셸 */
    public function shell(): View
    {
        return view('admin.shell', [
            'menu' => self::menu(),
            'user' => Auth::user(),
        ]);
    }

    /** 임베디드 화면 디스패치 */
    public function screen(Request $request, string $key): View
    {
        return match ($key) {
            'dashboard' => $this->dashboard(),
            'demand' => $this->demand($request),
            'workers' => $this->workers($request),
            'onboarding' => $this->onboarding($request),
            default => abort(404),
        };
    }

    private function dashboard(): View
    {
        return view('admin.screens.dashboard', [
            'stats' => [
                'workers' => Worker::count(),
                'demand' => DemandRequest::whereIn('status', [
                    DemandStatus::Submitted->value,
                    DemandStatus::Aggregated->value,
                ])->count(),
                'onboarding' => OnboardingSubmission::where('status', OnboardingStatus::Submitted->value)->count(),
                'sos' => SosAlert::where('status', 'open')->count(),
            ],
        ]);
    }

    private function demand(Request $request): View
    {
        $rows = DemandRequest::with(['farm', 'city'])
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.screens.demand', ['rows' => $rows]);
    }

    private function workers(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $rows = Worker::query()
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%"))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.screens.workers', ['rows' => $rows, 'q' => $q]);
    }

    /** 근로자 상세 — 개인정보 열람 감사 로그 (CLAUDE.md §7-6) */
    public function worker(Worker $worker): View
    {
        $worker->recordAccessBy(Auth::user(), 'console-detail');

        return view('admin.screens.worker_detail', ['worker' => $worker]);
    }

    private function onboarding(Request $request): View
    {
        $rows = OnboardingSubmission::with('worker')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.screens.onboarding', ['rows' => $rows]);
    }
}
