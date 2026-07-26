<?php

use App\Http\Middleware\EnsureNdnAdmin;
use App\Http\Middleware\EnsureWorkerToken;
use App\Http\Middleware\RecordAccessLog;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        ]);

        // 미로그인 웹 요청은 Fortify 기본 /login 이 아니라 운영 콘솔 로그인으로
        $middleware->redirectGuestsTo(fn () => route('admin.login'));

        // 접속·페이지 접근 로그 기록 (메인 비로그인 + 로그인 이후 모두)
        $middleware->web(append: [RecordAccessLog::class]);

        // 뷰어 타임존 쿠키는 JS(평문)로 심으므로 암호화 대상에서 제외 (LocalTime 이 읽음)
        $middleware->encryptCookies(except: ['ndn_tz']);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
