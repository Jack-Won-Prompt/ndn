<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('code') · N.D.N Korea</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--ink:#1A140F;--muted:#6B7280;--line:#E3E6EA}
    html,body{height:100%}
    body{
        font-family:"Pretendard Variable","Pretendard",-apple-system,BlinkMacSystemFont,"Segoe UI","Apple SD Gothic Neo","Malgun Gothic",sans-serif;
        color:var(--ink);background:#fff;
        display:grid;place-items:center;padding:32px;
        -webkit-font-smoothing:antialiased;word-break:keep-all;
    }
    .err{max-width:520px;width:100%;text-align:center}
    .err__mark{
        display:inline-grid;place-items:center;width:52px;height:52px;
        background:var(--ink);color:#fff;border-radius:12px;
        font-size:24px;font-weight:800;letter-spacing:.02em;margin-bottom:30px;
    }
    .err__code{
        font-size:clamp(64px,16vw,112px);font-weight:800;line-height:1;
        letter-spacing:-.03em;color:var(--ink);
    }
    .err__title{font-size:clamp(20px,3.4vw,26px);font-weight:700;margin:18px 0 0;letter-spacing:-.01em}
    .err__msg{font-size:16px;line-height:1.7;color:var(--muted);margin:14px auto 0;max-width:400px}
    .err__actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:34px}
    .err__btn{
        display:inline-flex;align-items:center;gap:8px;
        padding:13px 26px;font-family:inherit;font-size:15px;font-weight:700;
        border-radius:6px;border:1px solid transparent;cursor:pointer;text-decoration:none;
        transition:transform .18s,background .18s,border-color .18s,color .18s;
    }
    .err__btn:hover{transform:translateY(-2px)}
    .err__btn--dark{background:var(--ink);color:#fff}
    .err__btn--dark:hover{background:#000}
    .err__btn--line{border-color:var(--line);color:var(--ink)}
    .err__btn--line:hover{border-color:var(--ink);background:#F8F9FA}
    .err__foot{margin-top:44px;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:#9AA1AC}
</style>
</head>
<body>
    <main class="err">
        <div class="err__mark">N</div>
        <div class="err__code">@yield('code')</div>
        <h1 class="err__title">@yield('title')</h1>
        <p class="err__msg">@yield('message')</p>
        <div class="err__actions">
            <a class="err__btn err__btn--dark" href="{{ url('/') }}">홈으로 돌아가기</a>
            @yield('actions')
        </div>
        <p class="err__foot">N.D.N Korea</p>
    </main>
</body>
</html>
