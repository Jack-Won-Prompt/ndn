<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>유효하지 않은 초대 — N.D.N Korea</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
    body{margin:0;font-family:"Pretendard Variable","Pretendard",-apple-system,"Malgun Gothic",sans-serif;background:#F4F6F8;color:#1B1E24;display:flex;min-height:100vh;align-items:center;justify-content:center;text-align:center;-webkit-font-smoothing:antialiased;}
    .card{width:420px;max-width:92%;background:#fff;border:1px solid #E3E6EA;border-radius:16px;padding:40px 30px;box-shadow:0 10px 40px rgba(15,23,42,.08);}
    .icon{font-size:44px;}
    h1{font-size:20px;margin:14px 0 8px;}
    p{font-size:14px;color:#6B7280;line-height:1.6;margin:0;}
    a{display:inline-block;margin-top:22px;font-size:14px;font-weight:700;color:#1E9C92;text-decoration:none;border:1px solid #1E9C92;border-radius:8px;padding:10px 20px;}
    a:hover{background:#E9F6F4;}
</style>
</head>
<body>
    <div class="card">
        <div class="icon">🔒</div>
        <h1>유효하지 않은 초대입니다</h1>
        <p>이 초대 링크는 만료되었거나 이미 사용되었거나 철회되었습니다.<br>담당자에게 새 초대를 요청하세요.</p>
        <a href="{{ route('portal.login') }}">포털 로그인으로</a>
    </div>
</body>
</html>
