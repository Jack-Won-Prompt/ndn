<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>로그인 — N.D.N Korea 운영 콘솔</title>
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
<link rel="stylesheet" href="{{ asset('admin-assets/css/admin.css') }}">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-card__brand">
            <b>N.D.N Korea</b>
            <div>운영 콘솔</div>
        </div>

        @if ($errors->any())
            <div class="login-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.attempt') }}">
            @csrf
            <div class="login-field">
                <label for="email">이메일</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       autocomplete="username" required autofocus>
            </div>
            <div class="login-field">
                <label for="password">비밀번호</label>
                <input id="password" name="password" type="password"
                       autocomplete="current-password" required>
            </div>
            <button type="submit" class="login-btn">로그인</button>
        </form>

        <p class="login-hint">NDN 관리자(ndn_admin) 계정만 접근할 수 있습니다.</p>
    </div>
</div>
</body>
</html>
