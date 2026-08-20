<?php

use App\Http\Middleware\EnsureNdnAdmin;
use App\Http\Middleware\EnsurePortalUser;
use App\Http\Middleware\EnsureRequiredDocumentsAgreed;
use App\Http\Middleware\EnsureWorkerToken;
use App\Http\Middleware\RecordAccessLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // 근로자 앱 API 스코프 강제 (CLAUDE.md §9)
        $middleware->alias([
            'worker' => EnsureWorkerToken::class,
            'ndn_admin' => EnsureNdnAdmin::class,
            // 관리자 앱 API — User 토큰 + 포털 허용 역할 (worker 와 상호 배제)
            'portal' => EnsurePortalUser::class,
            // 필수 문서(의무사항·표준근로계약서 등) 미동의 시 다음 화면으로 못 넘어가게 차단
            'docs.agreed' => EnsureRequiredDocumentsAgreed::class,
        ]);

        // 미로그인 웹 요청은 Fortify 기본 /login 이 아니라 운영 콘솔 로그인으로.
        // 단 근로자 화면(/worker/*)은 근로자 로그인으로 보낸다 — 근로자를 관리자
        // 로그인 화면에 떨어뜨리면 자기 계정이 없는 줄 안다.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('worker', 'worker/*')
            ? route('worker.login')
            : route('admin.login'));

        // 접속·페이지 접근 로그 기록 (메인 비로그인 + 로그인 이후 모두)
        $middleware->web(append: [RecordAccessLog::class]);

        // 뷰어 타임존 쿠키는 JS(평문)로 심으므로 암호화 대상에서 제외 (LocalTime 이 읽음)
        // ndn_visitor 는 그 자체가 무작위 식별 토큰(비밀)이라 암호화 불필요 (SiteChatController)
        $middleware->encryptCookies(except: ['ndn_tz', 'ndn_visitor']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
