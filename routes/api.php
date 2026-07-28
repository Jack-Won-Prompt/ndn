<?php

declare(strict_types=1);

use App\Domains\Matching\Http\Controllers\Api\MyPlacementController;
use App\Domains\Monitoring\Http\Controllers\Api\MonthlyInterviewController;
use App\Domains\Onboarding\Http\Controllers\Api\ConsentController;
use App\Domains\Onboarding\Http\Controllers\Api\OnboardingController;
use App\Domains\Recruitment\Http\Controllers\Api\AuthController;
use App\Domains\Recruitment\Http\Controllers\Api\DashboardController;
use App\Domains\Recruitment\Http\Controllers\Api\WorkerProfileController;
use App\Domains\Settlement\Http\Controllers\Api\SettlementController;
use App\Domains\Support\Http\Controllers\Api\ChatController;
use App\Domains\Support\Http\Controllers\Api\SosController;
use App\Domains\Support\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\Admin\AdminAuthController;
use App\Http\Controllers\Api\Admin\ArrivalAdminController;
use App\Http\Controllers\Api\Admin\CandidateAdminController;
use App\Http\Controllers\Api\Admin\CaseworkAdminController;
use App\Http\Controllers\Api\Admin\ChatAdminController;
use App\Http\Controllers\Api\Admin\DashboardAdminController;
use App\Http\Controllers\Api\Admin\FieldWorkAdminController;
use App\Http\Controllers\Api\Admin\MatchingAdminController;
use App\Http\Controllers\Api\Admin\SosAdminController;
use App\Http\Controllers\Api\Admin\WorkerAdminController;
use App\Http\Controllers\Api\AppVersionController;
use App\Shared\Notifications\Http\Controllers\DeviceTokenController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 근로자 앱 API v1 (CLAUDE.md §9)
|--------------------------------------------------------------------------
| prefix /api/v1, Sanctum 토큰 인증, worker 미들웨어로 본인 리소스만 접근.
| 엔드포인트는 URL 에 worker_id 를 받지 않고 인증된 Worker 본인에서 파생한다.
*/

/*
|--------------------------------------------------------------------------
| 앱 버전 확인 (사이드로딩 배포)
|--------------------------------------------------------------------------
| 플레이스토어를 거치지 않아 자동 업데이트가 없다. 앱이 시작·복귀할 때 최신
| 버전을 물어본다. **인증 밖**에 둔다 — 강제 업데이트 대상 구버전은 로그인
| 자체가 실패할 수 있는데 그 상태에서도 안내는 띄워야 하기 때문이다.
*/
Route::get('v1/app/version', AppVersionController::class)->middleware('throttle:60,1');

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

    // 홈 대시보드 — 배정·입국·평가·진행 건수 요약 (로그인 후 첫 화면)
    Route::get('/dashboard', DashboardController::class);

    // 푸시 수신 기기 등록·해제 (FCM). 소유자는 인증된 근로자에서 파생한다.
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    // 근로 생활 평가 (월별 점검 6항목) — 본인 이력 조회 + 자가 평가 제출
    Route::get('/interviews', [MonthlyInterviewController::class, 'index']);
    Route::post('/interviews', [MonthlyInterviewController::class, 'store']);

    // 셀프 온보딩
    Route::get('/onboarding', [OnboardingController::class, 'show']);
    Route::post('/onboarding', [OnboardingController::class, 'store']);
    Route::post('/onboarding/submit', [OnboardingController::class, 'submit']);

    // 내 배정·입국 일정 (업무흐름 §4·§5) — 확정된 배정만
    Route::get('/my/placement', [MyPlacementController::class, 'show']);

    // 정착 서비스 신청 (업무흐름 §6) — 통장·보험·통신·유심
    Route::get('/settlements', [SettlementController::class, 'index']);
    Route::post('/settlements', [SettlementController::class, 'store']);

    // 동의 관리 (§7-4) — 본인 동의 상태 조회·동의·철회
    Route::get('/consents', [ConsentController::class, 'index']);
    Route::post('/consents/grant', [ConsentController::class, 'grant']);
    Route::post('/consents/revoke', [ConsentController::class, 'revoke']);

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

/*
|--------------------------------------------------------------------------
| 관리자 앱 API v1 (CLAUDE.md §9)
|--------------------------------------------------------------------------
| prefix /api/v1/admin, Sanctum 토큰 인증, portal 미들웨어.
|
| 근로자 API 와 토큰 종류가 다르다. 근로자 토큰은 portal 을 통과하지 못하고,
| 관리자 토큰은 worker 를 통과하지 못한다(서로 배제).
|
| 조회 범위는 역할별로 다르며(PortalScope) 상태 변경은 NDN 관리자만 가능하다.
*/
Route::prefix('v1/admin')->group(function () {
    Route::post('/auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:10,1');
});

Route::prefix('v1/admin')->middleware(['auth:sanctum', 'portal'])->group(function () {
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/auth/logout', [AdminAuthController::class, 'logout']);

    // 대시보드 — 긴급(SOS)·할 일·현황 집계 (로그인 후 첫 화면)
    Route::get('/dashboard', DashboardAdminController::class);

    // 푸시 수신 기기 등록·해제 (FCM). 근로자와 같은 컨트롤러, 소유자만 다르다.
    Route::post('/device-tokens', [DeviceTokenController::class, 'store']);
    Route::delete('/device-tokens', [DeviceTokenController::class, 'destroy']);

    // 근로자 — 목록·상세·가입 승인·재직 상태
    Route::get('/workers', [WorkerAdminController::class, 'index']);
    Route::get('/workers/{worker}', [WorkerAdminController::class, 'show'])->whereNumber('worker');
    Route::post('/workers/{worker}/approve', [WorkerAdminController::class, 'approve'])->whereNumber('worker');
    Route::post('/workers/{worker}/reject', [WorkerAdminController::class, 'reject'])->whereNumber('worker');
    Route::post('/workers/{worker}/status', [WorkerAdminController::class, 'updateStatus'])->whereNumber('worker');

    // 현지 면접 평가 (업무흐름 §2) — 모바일 평가 시트 + 보류 대기열
    Route::get('/candidates', [CandidateAdminController::class, 'index']);
    Route::post('/candidates/{candidate}/evaluate', [CandidateAdminController::class, 'evaluate'])
        ->whereNumber('candidate');
    Route::post('/candidates/promote', [CandidateAdminController::class, 'promote']);

    // 매칭 (업무흐름 §4) — 수요 → 추천 후보 → 배정 → 확정 → 입국 인계
    Route::get('/demands', [MatchingAdminController::class, 'demands']);
    Route::get('/demands/{demand}/candidates', [MatchingAdminController::class, 'candidates'])->whereNumber('demand');
    Route::get('/placements', [MatchingAdminController::class, 'placements']);
    Route::post('/placements', [MatchingAdminController::class, 'store']);
    Route::post('/placements/{placement}/confirm', [MatchingAdminController::class, 'confirm'])->whereNumber('placement');
    Route::post('/placements/{placement}/cancel', [MatchingAdminController::class, 'cancel'])->whereNumber('placement');

    // 입국한 근로자 관리 (업무흐름 §5)
    Route::get('/arrivals', [ArrivalAdminController::class, 'index']);
    Route::post('/arrivals/{arrival}', [ArrivalAdminController::class, 'update'])->whereNumber('arrival');
    Route::post('/arrivals/{arrival}/advance', [ArrivalAdminController::class, 'advance'])->whereNumber('arrival');

    // 긴급 SOS 상황판
    Route::get('/sos', [SosAdminController::class, 'index']);
    Route::post('/sos/{sos}/status', [SosAdminController::class, 'updateStatus'])->whereNumber('sos');

    // 현장 점검 (업무흐름 §7) — 농가 방문(사진)·근로자 인터뷰·GPS 체크인
    Route::get('/field/farms', [FieldWorkAdminController::class, 'farms']);
    Route::get('/farm-visits', [FieldWorkAdminController::class, 'index']);
    Route::post('/farm-visits', [FieldWorkAdminController::class, 'store']);
    Route::get('/farm-visits/{visit}/photos/{photo}', [FieldWorkAdminController::class, 'photo'])
        ->whereNumber(['visit', 'photo']);
    Route::post('/checkins', [FieldWorkAdminController::class, 'checkin']);

    // 처리 업무 — 온보딩 검수 · 민원 · 정착
    Route::get('/onboarding', [CaseworkAdminController::class, 'onboardingIndex']);
    Route::post('/onboarding/{submission}/review', [CaseworkAdminController::class, 'onboardingReview'])->whereNumber('submission');
    Route::get('/tickets', [CaseworkAdminController::class, 'ticketIndex']);
    Route::post('/tickets/{ticket}/status', [CaseworkAdminController::class, 'ticketStatus'])->whereNumber('ticket');
    Route::get('/settlements', [CaseworkAdminController::class, 'settlementIndex']);
    Route::post('/settlements/{settlement}/stage', [CaseworkAdminController::class, 'settlementStage'])->whereNumber('settlement');

    // 근로자와의 메시지 (자동 번역)
    Route::get('/chat/conversations', [ChatAdminController::class, 'conversations']);
    Route::post('/chat/open', [ChatAdminController::class, 'open']);
    Route::get('/chat/{conversation}/messages', [ChatAdminController::class, 'messages'])->whereNumber('conversation');
    Route::post('/chat/{conversation}/messages', [ChatAdminController::class, 'send'])->whereNumber('conversation');
});
