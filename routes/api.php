<?php

declare(strict_types=1);

use App\Domains\Monitoring\Http\Controllers\Api\MonthlyInterviewController;
use App\Domains\Onboarding\Http\Controllers\Api\OnboardingController;
use App\Domains\Recruitment\Http\Controllers\Api\AuthController;
use App\Domains\Recruitment\Http\Controllers\Api\WorkerProfileController;
use App\Domains\Support\Http\Controllers\Api\ChatController;
use App\Domains\Support\Http\Controllers\Api\SosController;
use App\Domains\Support\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 근로자 앱 API v1 (CLAUDE.md §9)
|--------------------------------------------------------------------------
| prefix /api/v1, Sanctum 토큰 인증, worker 미들웨어로 본인 리소스만 접근.
| 엔드포인트는 URL 에 worker_id 를 받지 않고 인증된 Worker 본인에서 파생한다.
*/

// 로그인은 토큰 발급 전이므로 인증 미들웨어 밖에 둔다. 무차별 대입 방지로 throttle 적용.
Route::prefix('v1')->group(function () {
    // 셀프 가입 (관리자 승인제) — 무차별 대입 방지로 throttle
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::prefix('v1')->middleware(['auth:sanctum', 'worker'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // 본인 프로필
    Route::get('/me', [WorkerProfileController::class, 'show']);

    // 근로 생활 평가 (월별 점검 6항목) — 본인 이력 조회 + 자가 평가 제출
    Route::get('/interviews', [MonthlyInterviewController::class, 'index']);
    Route::post('/interviews', [MonthlyInterviewController::class, 'store']);

    // 셀프 온보딩
    Route::get('/onboarding', [OnboardingController::class, 'show']);
    Route::post('/onboarding', [OnboardingController::class, 'store']);
    Route::post('/onboarding/submit', [OnboardingController::class, 'submit']);

    // 긴급 SOS — rate limit 완화(긴급 상황). 좌표는 이 요청 본문으로만 수신.
    Route::post('/sos', [SosController::class, 'store'])->middleware('throttle:60,1');

    // 민원 (문제신고/문의/연장/조기귀국)
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);

    // 채팅 (근로자 ↔ NDN·시청·농가) — 자국어 작성, 자동 번역
    Route::get('/chat/conversations', [ChatController::class, 'conversations']);
    Route::post('/chat/open', [ChatController::class, 'open']);
    Route::get('/chat/{conversation}/messages', [ChatController::class, 'messages'])->whereNumber('conversation');
    Route::post('/chat/{conversation}/messages', [ChatController::class, 'send'])->whereNumber('conversation');
    Route::patch('/chat/{conversation}/messages/{message}', [ChatController::class, 'update'])->whereNumber(['conversation', 'message']);
    Route::delete('/chat/{conversation}/messages/{message}', [ChatController::class, 'destroy'])->whereNumber(['conversation', 'message']);
    Route::get('/chat/{conversation}/files/{message}', [ChatController::class, 'file'])->whereNumber(['conversation', 'message']);
});
