<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0B0F16">
<title>@yield('code') · N.D.N Korea</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
{{-- 오류 화면은 앱이 망가진 상태에서도 떠야 한다.
     외부 의존 없이 사이트 스타일시트 하나만 쓴다. --}}
<link rel="stylesheet" href="{{ asset('site/assets/css/ndn.css') }}?v={{ @filemtime(public_path('site/assets/css/ndn.css')) }}">
</head>
<body>
    <main class="nd-error">
        <div class="nd-error__in">
            <div class="nd-error__code">@yield('code')</div>
            <h1>@yield('title')</h1>
            <p>@yield('message')</p>
            <div class="nd-btnrow">
                <a class="nd-btn nd-btn--paper" href="{{ url('/') }}">홈으로 돌아가기</a>
                @yield('actions')
            </div>
            <p style="margin-top:44px;font-size:12px;letter-spacing:.14em;text-transform:uppercase;color:var(--nd-on-ink-3)">
                N.D.N Korea
            </p>
        </div>
    </main>
</body>
</html>
