<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B0F16">
<title>유효하지 않은 초대 — N.D.N Korea</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
<link rel="stylesheet" href="{{ asset('site/assets/css/ndn.css') }}?v={{ @filemtime(public_path('site/assets/css/ndn.css')) }}">
</head>
<body>
    <main class="nd-error">
        <div class="nd-error__in">
            <div class="nd-error__code">초대</div>
            <h1>유효하지 않은 초대입니다</h1>
            <p>이 초대 링크는 만료되었거나 이미 사용되었거나 철회되었습니다.<br>담당자에게 새 초대를 요청하세요.</p>
            <div class="nd-btnrow">
                <a class="nd-btn nd-btn--paper" href="{{ route('portal.login') }}">포털 로그인으로</a>
            </div>
        </div>
    </main>
</body>
</html>
