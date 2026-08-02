<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>협력 포털 로그인 — N.D.N Korea</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
<link rel="stylesheet" href="{{ asset('admin-assets/css/admin.css') }}?v={{ @filemtime(public_path('admin-assets/css/admin.css')) }}">
</head>
<body>
<div class="login-wrap">
    <div class="login-card">
        <div class="login-card__brand">
            <b>N.D.N 협력 포털</b>
            <div>시청 · 농가 · 해외협력사</div>
        </div>

        @if ($errors->any())
            <div class="login-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('portal.login.attempt') }}">
            @csrf
            <div class="login-field">
                <label for="email">이메일</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}"
                       autocomplete="username" required autofocus>
            </div>
            <div class="login-field">
                <label for="password">비밀번호</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>
            </div>
            <label class="login-remember">
                <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                로그인 상태 유지
            </label>
            <button type="submit" class="login-btn">로그인</button>
        </form>

        <p class="login-hint">시청·농가·해외협력사 계정으로 로그인하세요. 관리자는 <a href="{{ url('admin/login') }}">운영 콘솔</a>.</p>

        @if (config('ndn.show_test_logins'))
            <div class="login-testaccts">
                <div class="login-testaccts__head">테스트 계정 · 클릭하면 자동 입력</div>
                <ul>
                    @foreach ([
                        ['city@ndn.test', '시청 담당자'],
                        ['farm@ndn.test', '농가'],
                        ['agency@ndn.test', '해외 협력사(송출기관)'],
                        ['partner@ndn.test', '제휴 대리점'],
                    ] as [$acctEmail, $acctLabel])
                        <li>
                            <button type="button" class="login-testacct" data-email="{{ $acctEmail }}" data-pw="password">
                                <span class="login-testacct__role">{{ $acctLabel }}</span>
                                <span class="login-testacct__email">{{ $acctEmail }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                <div class="login-testaccts__foot">비밀번호 공통 <code>password</code></div>
            </div>
            <script>
                document.querySelectorAll('.login-testacct').forEach(function (b) {
                    b.addEventListener('click', function () {
                        document.getElementById('email').value = b.dataset.email;
                        document.getElementById('password').value = b.dataset.pw;
                        document.getElementById('password').focus();
                    });
                });
            </script>
        @endif
    </div>
</div>
</body>
</html>
