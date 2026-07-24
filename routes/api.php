<?php

declare(strict_types=1);

use App\Domains\Onboarding\Http\Controllers\Api\OnboardingController;
use App\Domains\Recruitment\Http\Controllers\Api\WorkerProfileController;
use App\Domains\Support\Http\Controllers\Api\SosController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 근로자 앱 API v1 (CLAUDE.md §9)
|--------------------------------------------------------------------------
| prefix /api/v1, Sanctum 토큰 인증, worker 미들웨어로 본인 리소스만 접근.
| 엔드포인트는 URL 에 worker_id 를 받지 않고 인증된 Worker 본인에서 파생한다.
*/
Route::prefix('v1')->middleware(['auth:sanctum', 'worker'])->group(function () {
    // 본인 프로필
    Route::get('/me', [WorkerProfileController::class, 'show']);

    // 셀프 온보딩
    Route::get('/onboarding', [OnboardingController::class, 'show']);
    Route::post('/onboarding', [OnboardingController::class, 'store']);
    Route::post('/onboarding/submit', [OnboardingController::class, 'submit']);

    // 긴급 SOS — rate limit 완화(긴급 상황). 좌표는 이 요청 본문으로만 수신.
    Route::post('/sos', [SosController::class, 'store'])->middleware('throttle:60,1');
});
