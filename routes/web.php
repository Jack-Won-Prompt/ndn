<?php

declare(strict_types=1);

use App\Domains\Demand\Http\Controllers\DemandRequestController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\ConsoleController;
use App\Http\Controllers\Admin\CandidateGridController;
use App\Http\Controllers\Admin\DemandGridController;
use App\Http\Controllers\Admin\WorkerGridController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 회사소개 사이트 (Blade)
|--------------------------------------------------------------------------
| 정적 HTML 을 Blade 뷰로 전환. 공통 골격은 site.layout, 각 페이지는 <main> 만.
| URL 은 /ndn, /ndn/about … 처럼 .html 없이 깔끔하게. 에셋은 여전히 public/site/assets.
*/
Route::view('/', 'site.home', ['active' => 'home'])->name('site.home');
Route::view('/about', 'site.about', ['active' => 'about'])->name('site.about');
Route::view('/services', 'site.services', ['active' => 'services'])->name('site.services');
Route::view('/worker-support', 'site.worker', ['active' => 'worker'])->name('site.worker');
Route::view('/partners', 'site.partners', ['active' => 'partners'])->name('site.partners');
Route::view('/contact', 'site.contact', ['active' => 'contact'])->name('site.contact');

/*
|--------------------------------------------------------------------------
| Demand 도메인 (농가 수요 신청)
|--------------------------------------------------------------------------
| 인증 필요. 세부 인가는 각 컨트롤러 액션의 Policy 에서 처리한다.
*/
Route::middleware('auth')->group(function () {
    Route::get('/demand', [DemandRequestController::class, 'index'])->name('demand.index');
    Route::get('/demand/{demand}', [DemandRequestController::class, 'show'])->name('demand.show');
    Route::post('/farms/{farm}/demand', [DemandRequestController::class, 'store'])->name('demand.store');
    Route::post('/demand/{demand}/submit', [DemandRequestController::class, 'submit'])->name('demand.submit');
});

/*
|--------------------------------------------------------------------------
| NDN 운영 콘솔 (/admin) — MDI 탭 워크스페이스 (fulfillment식)
|--------------------------------------------------------------------------
| ndn_admin 역할만 접근. 셸은 사이드바+탭바만, 업무 화면은 /admin/screen/* iframe.
*/
Route::prefix('admin')->group(function () {
    // 인증 (게스트 접근)
    Route::get('/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
    Route::post('/login', [AdminAuthController::class, 'login'])->name('admin.login.attempt');
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    // 콘솔 (ndn_admin 전용)
    Route::middleware('ndn_admin')->group(function () {
        Route::get('/', [ConsoleController::class, 'shell'])->name('admin.shell');
        Route::post('/settings', [ConsoleController::class, 'saveSettings'])->name('admin.settings.save');
        Route::get('/reports/monthly', [ConsoleController::class, 'monthlyReport'])->name('admin.reports.monthly');
        Route::post('/tickets/{ticket}/status', [ConsoleController::class, 'updateTicketStatus'])
            ->whereNumber('ticket')->name('admin.tickets.status');
        Route::get('/screen/workers/{worker}', [ConsoleController::class, 'worker'])
            ->whereNumber('worker')->name('admin.screen.worker');
        // wwGrid CRUD — 수요 신청
        Route::post('/grid/demand/save', [DemandGridController::class, 'save'])->name('admin.grid.demand.save');
        Route::post('/grid/demand/import', [DemandGridController::class, 'import'])->name('admin.grid.demand.import');
        // wwGrid CRUD — 근로자
        Route::post('/grid/workers/save', [WorkerGridController::class, 'save'])->name('admin.grid.workers.save');
        Route::post('/grid/workers/import', [WorkerGridController::class, 'import'])->name('admin.grid.workers.import');
        // wwGrid CRUD — 후보자
        Route::post('/grid/candidates/save', [CandidateGridController::class, 'save'])->name('admin.grid.candidates.save');
        Route::post('/grid/candidates/import', [CandidateGridController::class, 'import'])->name('admin.grid.candidates.import');

        Route::get('/onboarding/{submission}', [ConsoleController::class, 'onboardingDetail'])
            ->whereNumber('submission')->name('admin.onboarding.detail');
        Route::get('/onboarding/{submission}/signature', [ConsoleController::class, 'onboardingSignature'])
            ->whereNumber('submission')->name('admin.onboarding.signature');
        Route::get('/screen/{key}', [ConsoleController::class, 'screen'])
            ->where('key', '[a-z_]+')->name('admin.screen');
    });
});
