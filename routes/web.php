<?php

declare(strict_types=1);

use App\Domains\Demand\Http\Controllers\DemandRequestController;
use App\Domains\Recruitment\Http\Controllers\Web\WorkerApplyController;
use App\Domains\Recruitment\Http\Controllers\Web\WorkerAuthController;
use App\Domains\Recruitment\Http\Controllers\Web\WorkerHomeController;
use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\Admin\AccountDeletionAdminController;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BaseInfoGridController;
use App\Http\Controllers\Admin\CandidateGridController;
use App\Http\Controllers\Admin\ConsoleController;
use App\Http\Controllers\Admin\DemandGridController;
use App\Http\Controllers\Admin\EvaluationItemGridController;
use App\Http\Controllers\Admin\FarmVisitController;
use App\Http\Controllers\Admin\InquiryController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\LifeChecklistController;
use App\Http\Controllers\Admin\MatchingController;
use App\Http\Controllers\Admin\NoticeController;
use App\Http\Controllers\Admin\RegionController;
use App\Http\Controllers\Admin\RequiredDocumentAdminController;
use App\Http\Controllers\Admin\ServiceRequestController;
use App\Http\Controllers\Admin\SignupApprovalController;
use App\Http\Controllers\Admin\SosController;
use App\Http\Controllers\Admin\TicketGridController;
use App\Http\Controllers\Admin\WorkerExitController;
use App\Http\Controllers\Admin\WorkerFileController;
use App\Http\Controllers\Admin\WorkerGridController;
use App\Http\Controllers\Admin\WorkReviewController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\InvitationAcceptController;
use App\Http\Controllers\Portal\PartnerSettlementController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\SiteChatController;
use App\Http\Controllers\SiteController;
use App\Models\Setting;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 회사소개 사이트 (Blade)
|--------------------------------------------------------------------------
| 정적 HTML 을 Blade 뷰로 전환. 공통 골격은 site.layout, 각 페이지는 <main> 만.
| URL 은 /ndn, /ndn/about … 처럼 .html 없이 깔끔하게. 에셋은 여전히 public/site/assets.
*/
// 회사소개 사이트 — 선택 언어(ko/bn/lo/si/vi)로 자동 번역 렌더 (SiteController)
Route::get('/', [SiteController::class, 'page'])->defaults('key', 'home')->name('site.home');
Route::get('/about', [SiteController::class, 'page'])->defaults('key', 'about')->name('site.about');
Route::get('/services', [SiteController::class, 'page'])->defaults('key', 'services')->name('site.services');
Route::get('/worker-support', [SiteController::class, 'page'])->defaults('key', 'worker')->name('site.worker');
Route::get('/partners', [SiteController::class, 'page'])->defaults('key', 'partners')->name('site.partners');
Route::get('/contact', [SiteController::class, 'page'])->defaults('key', 'contact')->name('site.contact');
// 경로는 프로젝트 루트의 물리 디렉터리(lang/)와 겹치지 않게 set-language 를 쓴다.
Route::get('/set-language/{locale}', [SiteController::class, 'setLocale'])->name('site.lang');

// 앱 다운로드 — 관리자 설정(app.play_store_url)이 있으면 플레이스토어로, 없으면 홈페이지로.
// QR·설치 링크가 이 고정 주소를 가리켜, 등록 후 목적지만 바뀌고 QR 은 그대로 쓴다.
Route::get('/get-app', function () {
    $url = Setting::get('app.play_store_url');

    return redirect()->away(filled($url) ? $url : route('site.home'));
})->name('app.download');

/*
|--------------------------------------------------------------------------
| 근로자 웹 (가입 · 로그인 · 본인 화면)
|--------------------------------------------------------------------------
| 앱을 깔 수 없는 환경에서도 지원할 수 있게 웹에도 같은 입구를 낸다.
| 저장은 앱과 같은 Action 을 타므로 두 경로의 규칙이 어긋나지 않는다.
| 화면은 회사소개 사이트 레이아웃이라 방문자 언어로 자동 번역된다(§6).
*/
Route::prefix('apply')->group(function () {
    Route::get('/', [WorkerApplyController::class, 'create'])->name('site.apply');
    Route::post('/', [WorkerApplyController::class, 'store'])
        ->middleware('throttle:10,1')->name('site.apply.store');
    Route::get('/done', [WorkerApplyController::class, 'done'])->name('site.apply.done');

    // 보완 제출 — 메일의 기한부 서명 링크로만 열린다(로그인 없음).
    Route::get('/supplement/{worker}', [WorkerApplyController::class, 'supplement'])
        ->middleware('signed')->whereNumber('worker')->name('site.apply.supplement');
    Route::post('/supplement/{worker}', [WorkerApplyController::class, 'storeSupplement'])
        ->middleware(['signed', 'throttle:10,1'])->whereNumber('worker')->name('site.apply.supplement.store');
});

Route::prefix('worker')->group(function () {
    // 게스트
    Route::get('/login', [WorkerAuthController::class, 'showLogin'])->name('worker.login');
    Route::post('/login', [WorkerAuthController::class, 'login'])
        ->middleware('throttle:10,1')->name('worker.login.attempt');

    Route::get('/forgot-password', [WorkerAuthController::class, 'showForgot'])->name('worker.password.request');
    Route::post('/forgot-password', [WorkerAuthController::class, 'sendResetLink'])
        ->middleware('throttle:6,1')->name('worker.password.email');
    Route::get('/reset-password/{token}', [WorkerAuthController::class, 'showReset'])->name('worker.password.reset');
    Route::post('/reset-password', [WorkerAuthController::class, 'reset'])
        ->middleware('throttle:6,1')->name('worker.password.update');

    // 로그인 후 — 자기 근무지와 본인 정보만
    Route::middleware('auth:worker')->group(function () {
        Route::get('/', [WorkerHomeController::class, 'show'])->name('worker.home');
        Route::get('/profile', [WorkerHomeController::class, 'edit'])->name('worker.profile');
        Route::post('/profile', [WorkerHomeController::class, 'update'])
            ->middleware('throttle:20,1')->name('worker.profile.update');
        Route::get('/files/{file}', [WorkerHomeController::class, 'file'])
            ->whereNumber('file')->name('worker.files.show');
        Route::post('/logout', [WorkerAuthController::class, 'logout'])->name('worker.logout');
    });
});

// 법적 고지 (플레이스토어 제출용 — 공개·비로그인). 개인정보처리방침·이용약관·계정 삭제 요청
Route::get('/privacy', [SiteController::class, 'page'])->defaults('key', 'privacy')->name('site.privacy');
Route::get('/terms', [SiteController::class, 'page'])->defaults('key', 'terms')->name('site.terms');
Route::get('/account-deletion', [AccountDeletionController::class, 'show'])->name('legal.account-deletion');
Route::post('/account-deletion', [AccountDeletionController::class, 'store'])
    ->middleware('throttle:10,1')->name('legal.account-deletion.store');

// 회사소개 사이트 우하단 "문의하기" 실시간 채팅 (익명 방문자 ↔ NDN 관리자, 로그인 불필요)
Route::post('/site-chat/message', [SiteChatController::class, 'message'])
    ->middleware('throttle:20,1')->name('site.chat.message');
Route::get('/site-chat/poll', [SiteChatController::class, 'poll'])
    ->middleware('throttle:120,1')->name('site.chat.poll');

/*
|--------------------------------------------------------------------------
| Demand 도메인 (농가 수요 신청)
|--------------------------------------------------------------------------
| 인증 필요. 세부 인가는 각 컨트롤러 액션의 Policy 에서 처리한다.
*/
Route::middleware('auth')->group(function () {
    Route::get('/demand', [DemandRequestController::class, 'index'])->name('demand.index');
    Route::get('/demand/create', [DemandRequestController::class, 'create'])->name('demand.create');
    Route::get('/demand/{demand}', [DemandRequestController::class, 'show'])->whereNumber('demand')->name('demand.show');
    Route::post('/farms/{farm}/demand', [DemandRequestController::class, 'store'])->name('demand.store');
    Route::post('/demand/{demand}/submit', [DemandRequestController::class, 'submit'])->name('demand.submit');
});

/*
| 시청·농가·해외협력사 포털 (컨트롤러에서 인증 처리)
*/
Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('/login', [PortalController::class, 'showLogin'])->name('login');
    Route::post('/login', [PortalController::class, 'login'])->name('login.attempt');
    Route::post('/logout', [PortalController::class, 'logout'])->name('logout');
    Route::get('/', [PortalController::class, 'index'])->name('index');

    // 제휴 대리점 — 배정된 정착 서비스 건 조회·처리 (스코프+Policy 이중 방어, §7-4·§7-5)
    Route::middleware('auth')->prefix('settlements')->name('settlements.')->group(function () {
        Route::get('/', [PartnerSettlementController::class, 'index'])->name('index');
        Route::get('/{settlement}', [PartnerSettlementController::class, 'show'])->whereNumber('settlement')->name('show');
        Route::post('/{settlement}/process', [PartnerSettlementController::class, 'process'])->whereNumber('settlement')->name('process');
        Route::post('/{settlement}/documents', [PartnerSettlementController::class, 'uploadDocument'])->whereNumber('settlement')->name('documents.store');
        Route::get('/{settlement}/documents/{document}', [PartnerSettlementController::class, 'downloadDocument'])->whereNumber(['settlement', 'document'])->name('documents.show');
    });
});

/*
| 조직 초대 수락 (공개 · 비인증) — 시청·농가·송출·대리점 초대 링크
*/
Route::get('/invite/{token}', [InvitationAcceptController::class, 'show'])->name('invite.show');
Route::post('/invite/{token}', [InvitationAcceptController::class, 'accept'])->name('invite.accept');

/*
| 채팅 (조직 사용자: NDN·시청·농가·해외협력사) — 근로자는 /api/v1/chat
*/
Route::middleware('auth')->prefix('chat')->name('chat.')->group(function () {
    Route::get('/conversations', [ChatController::class, 'conversations'])->name('conversations');
    Route::get('/contacts', [ChatController::class, 'contacts'])->name('contacts');
    Route::get('/search-workers', [ChatController::class, 'searchWorkers'])->name('search-workers');
    Route::post('/open', [ChatController::class, 'open'])->name('open');
    Route::get('/{conversation}/messages', [ChatController::class, 'messages'])->whereNumber('conversation')->name('messages');
    Route::post('/{conversation}/messages', [ChatController::class, 'send'])->whereNumber('conversation')->name('send');
    Route::patch('/{conversation}/messages/{message}', [ChatController::class, 'update'])->whereNumber(['conversation', 'message'])->name('update');
    Route::delete('/{conversation}/messages/{message}', [ChatController::class, 'destroy'])->whereNumber(['conversation', 'message'])->name('destroy');
    Route::get('/{conversation}/files/{message}', [ChatController::class, 'file'])->whereNumber(['conversation', 'message'])->name('file');
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
        Route::get('/candidates/{candidate}', [CandidateGridController::class, 'show'])
            ->whereNumber('candidate')->name('admin.candidates.show');
        // wwGrid CRUD — 면접 평가 체크리스트 항목 (운영 중 조정)
        Route::post('/grid/evaluation-items/save', [EvaluationItemGridController::class, 'save'])
            ->name('admin.grid.evaluation-items.save');
        // wwGrid CRUD — 기준정보(농가·지자체)
        Route::post('/grid/cities/save', [BaseInfoGridController::class, 'citySave'])->name('admin.grid.cities.save');
        Route::post('/grid/farms/save', [BaseInfoGridController::class, 'farmSave'])->name('admin.grid.farms.save');
        Route::post('/grid/farms/import', [BaseInfoGridController::class, 'farmImport'])->name('admin.grid.farms.import');
        // wwGrid — 민원 상태 저장
        Route::post('/grid/tickets/save', [TicketGridController::class, 'save'])->name('admin.grid.tickets.save');

        // 정착 처리보드 — 대리점 배정(§7-4 동의 없으면 거부)
        Route::post('/settlements/{settlement}/assign', [ConsoleController::class, 'assignSettlement'])
            ->whereNumber('settlement')->name('admin.settlements.assign');

        // 계정 삭제 요청 처리 (Google Play 데이터 삭제 정책)
        Route::post('/account-deletions/{accountDeletionRequest}/process', [AccountDeletionAdminController::class, 'process'])
            ->whereNumber('accountDeletionRequest')->name('admin.account-deletions.process');

        // 근로자 공지사항 발송 (FCM 푸시 + 인앱)
        Route::post('/notices', [NoticeController::class, 'store'])->name('admin.notices.store');

        // 홈페이지 문의하기 (방문자 대화 — 채팅과 분리된 별도 화면)
        Route::get('/inquiries/conversations', [InquiryController::class, 'conversations'])->name('admin.inquiries.conversations');
        Route::get('/inquiries/{conversation}/messages', [InquiryController::class, 'messages'])
            ->whereNumber('conversation')->name('admin.inquiries.messages');
        Route::post('/inquiries/{conversation}/messages', [InquiryController::class, 'send'])
            ->whereNumber('conversation')->name('admin.inquiries.send');

        // 근로자 가입 승인 (셀프 가입 승인 큐)
        Route::get('/signups/{worker}', [SignupApprovalController::class, 'show'])
            ->whereNumber('worker')->name('admin.signups.show');
        // 합격 / 보류 / 불합격 — 합격은 계정 활성화와 FCM 알림까지 함께 간다.
        Route::post('/signups/{worker}/screen', [SignupApprovalController::class, 'screen'])
            ->whereNumber('worker')->name('admin.signups.screen');
        // 보완 요청 — 기한부 서명 링크를 근로자 이메일로 보낸다.
        Route::post('/signups/{worker}/supplement', [SignupApprovalController::class, 'requestSupplement'])
            ->whereNumber('worker')->name('admin.signups.supplement');

        // 필수 확인·동의 문서 — 언어별 본문 편집 + 버전 관리
        Route::get('/required-documents/{requiredDocument}', [RequiredDocumentAdminController::class, 'show'])
            ->whereNumber('requiredDocument')->name('admin.required-documents.show');
        // 원본 서식 내려받기 — 파일은 storage 에 있어 이 라우트로만 나간다
        Route::get('/required-documents/{requiredDocument}/file', [RequiredDocumentAdminController::class, 'download'])
            ->whereNumber('requiredDocument')->name('admin.required-documents.file');
        Route::post('/required-documents/{requiredDocument}', [RequiredDocumentAdminController::class, 'update'])
            ->whereNumber('requiredDocument')->name('admin.required-documents.update');
        // 원본 서식 올리기·떼기 — 이걸 붙이면 본문을 옮겨 적지 않고도 문서를 켤 수 있다
        Route::post('/required-documents/{requiredDocument}/file', [RequiredDocumentAdminController::class, 'uploadFile'])
            ->whereNumber('requiredDocument')->name('admin.required-documents.file.upload');
        Route::delete('/required-documents/{requiredDocument}/file', [RequiredDocumentAdminController::class, 'removeFile'])
            ->whereNumber('requiredDocument')->name('admin.required-documents.file.remove');

        // 농가↔근로자 매칭 — 배정 생성·확정·취소. 지금까지 관리자 앱에만 있던 기능이다.
        // 농가에서 출발하는 길 — 농가를 등록한 자리에서 바로 사람을 붙인다.
        Route::get('/matching/farms/{farm}', [MatchingController::class, 'farm'])
            ->whereNumber('farm')->name('admin.matching.farm');
        Route::post('/matching/farms/{farm}/demand', [MatchingController::class, 'storeDemand'])
            ->whereNumber('farm')->name('admin.matching.demand.store');
        Route::get('/matching/{demand}', [MatchingController::class, 'show'])
            ->whereNumber('demand')->name('admin.matching.show');
        Route::post('/matching/placements', [MatchingController::class, 'store'])
            ->name('admin.matching.store');
        // 배정 현황 표에서 체크한 건을 한 번에 (셀 안에 버튼을 둘 수 없어 툴바로 처리한다)
        Route::post('/matching/placements/bulk', [MatchingController::class, 'bulk'])
            ->name('admin.matching.bulk');
        Route::post('/matching/placements/{placement}/confirm', [MatchingController::class, 'confirm'])
            ->whereNumber('placement')->name('admin.matching.confirm');
        Route::post('/matching/placements/{placement}/cancel', [MatchingController::class, 'cancel'])
            ->whereNumber('placement')->name('admin.matching.cancel');

        // 지역별 모집·배치 — 시군 드릴다운(농가별 배치 인원)
        Route::get('/regions/{city}', [RegionController::class, 'show'])
            ->whereNumber('city')->name('admin.regions.show');

        // SR(Service Request) — 콘솔 상단 SR 버튼. 등록 → 담당자 답글 → 상태 관리.
        Route::post('/service-requests', [ServiceRequestController::class, 'store'])
            ->name('admin.service-requests.store');
        Route::get('/service-requests/{serviceRequest}', [ServiceRequestController::class, 'show'])
            ->whereNumber('serviceRequest')->name('admin.service-requests.show');
        Route::post('/service-requests/{serviceRequest}/replies', [ServiceRequestController::class, 'reply'])
            ->whereNumber('serviceRequest')->name('admin.service-requests.reply');
        Route::post('/service-requests/{serviceRequest}/status', [ServiceRequestController::class, 'updateStatus'])
            ->whereNumber('serviceRequest')->name('admin.service-requests.status');

        // 긴급 SOS — 확인·종료 처리 (상황판은 화면 디스패치가 그린다)
        Route::post('/sos/{sos}/status', [SosController::class, 'updateStatus'])
            ->whereNumber('sos')->name('admin.sos.status');

        // 근무상태 종합 점검표 — 작성·상세·서명 이미지(§12)
        // 월별 점검(6항목) 직접 입력이 있던 자리다. 그쪽은 폐기됐다.
        Route::post('/work-reviews', [WorkReviewController::class, 'store'])->name('admin.work-reviews.store');
        // 관계기관 제출 — 목록에서 고른 점검표를 PDF 로 첨부해 이메일로 보낸다
        Route::post('/work-reviews/share', [WorkReviewController::class, 'share'])
            ->name('admin.work-reviews.share');
        Route::get('/work-reviews/{workReview}', [WorkReviewController::class, 'show'])
            ->whereNumber('workReview')->name('admin.work-reviews.show');
        // 서명 파일은 storage 에 있어 이 라우트로만 나간다
        Route::get('/work-reviews/{workReview}/signature/{role}', [WorkReviewController::class, 'signature'])
            ->whereNumber('workReview')->whereAlpha('role')->name('admin.work-reviews.signature');
        // 관공서 제출용 PDF — 인적사항이 들어가므로 열람 기록을 남긴다
        Route::get('/work-reviews/{workReview}/pdf', [WorkReviewController::class, 'pdf'])
            ->whereNumber('workReview')->name('admin.work-reviews.pdf');

        // 근로자 개인 서류 — 여권 사본·건강검진 등. 본사가 보관한다.
        // 파일은 storage 에 있어 이 라우트로만 나가며 열람 기록을 남긴다(§7-6).
        Route::post('/workers/{worker}/files', [WorkerFileController::class, 'store'])
            ->whereNumber('worker')->name('admin.workers.files.store');
        Route::get('/workers/{worker}/files/{file}', [WorkerFileController::class, 'show'])
            ->whereNumber(['worker', 'file'])->name('admin.workers.files.show');
        Route::delete('/workers/{worker}/files/{file}', [WorkerFileController::class, 'destroy'])
            ->whereNumber(['worker', 'file'])->name('admin.workers.files.destroy');

        // 조기 귀국·이탈 — 사건 등록과 결정. 결정이 근로자 상태·배정에 함께 반영된다.
        Route::post('/worker-exits', [WorkerExitController::class, 'store'])
            ->name('admin.worker-exits.store');
        Route::get('/worker-exits/{workerExit}', [WorkerExitController::class, 'show'])
            ->whereNumber('workerExit')->name('admin.worker-exits.show');
        Route::post('/worker-exits/{workerExit}/advance', [WorkerExitController::class, 'advance'])
            ->whereNumber('workerExit')->name('admin.worker-exits.advance');

        // 생활 체크리스트 — 항목 문구 편집 (체크는 근로자 본인만 한다)
        Route::post('/life-checklist/items/{item}', [LifeChecklistController::class, 'updateItem'])
            ->whereNumber('item')->name('admin.life-checklist.item.update');

        // 농가 월별 방문 점검 (본사)
        Route::post('/farm-visits', [FarmVisitController::class, 'store'])->name('admin.farm-visits.store');
        Route::get('/farm-visits/farms/{farm}/workers', [FarmVisitController::class, 'workers'])
            ->whereNumber('farm')->name('admin.farm-visits.workers');
        Route::get('/farm-visits/workers/{worker}/reviews', [FarmVisitController::class, 'workerHistory'])
            ->whereNumber('worker')->name('admin.farm-visits.worker-history');
        Route::get('/farm-visits/{farmVisit}', [FarmVisitController::class, 'show'])
            ->whereNumber('farmVisit')->name('admin.farm-visits.show');
        Route::get('/farm-visits/{farmVisit}/photos/{photo}', [FarmVisitController::class, 'photo'])
            ->whereNumber(['farmVisit', 'photo'])->name('admin.farm-visits.photo');

        // 조직 초대 관리
        Route::post('/invitations/send', [InvitationController::class, 'send'])->name('admin.invitations.send');
        Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])
            ->whereNumber('invitation')->name('admin.invitations.resend');
        Route::post('/invitations/{invitation}/revoke', [InvitationController::class, 'revoke'])
            ->whereNumber('invitation')->name('admin.invitations.revoke');

        Route::get('/onboarding/{submission}', [ConsoleController::class, 'onboardingDetail'])
            ->whereNumber('submission')->name('admin.onboarding.detail');
        Route::get('/onboarding/{submission}/signature', [ConsoleController::class, 'onboardingSignature'])
            ->whereNumber('submission')->name('admin.onboarding.signature');
        // 하이픈 포함 — account-deletions, service-requests 같은 키가 있다.
        Route::get('/screen/{key}', [ConsoleController::class, 'screen'])
            ->where('key', '[a-z_-]+')->name('admin.screen');
    });
});
