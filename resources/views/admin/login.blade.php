<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B0F16">
<title>로그인 — N.D.N Korea 운영 콘솔</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
<link rel="stylesheet" href="{{ asset('site/assets/css/ndn.css') }}?v={{ @filemtime(public_path('site/assets/css/ndn.css')) }}">
</head>
<body>
<div class="nd-auth">

    {{-- 왼쪽 잉크 판 — 좁은 화면에서는 감춘다(로그인 폼이 먼저 보여야 한다). --}}
    <aside class="nd-auth__side">
        <a class="nd-logo" href="{{ url('/') }}">
            <span class="nd-logo__mark" style="color:#fff">N<i>.</i>D<i>.</i>N</span>
            <span class="nd-logo__sub">Korea</span>
        </a>
        <h2 class="nd-h1">운영 콘솔</h2>
        <p>
            수요 신청부터 모집·배치·정착·사후관리까지, 계절근로자 업무 전 과정을
            한 화면에서 운영합니다.
        </p>
    </aside>

    <main class="nd-auth__main">
        <div class="nd-auth__box">
            <h1 class="nd-h2">로그인</h1>
            <p>NDN 관리자(ndn_admin) 계정만 접근할 수 있습니다.</p>

            @if ($errors->any())
                <div class="nd-note nd-note--err" style="margin-top:20px" role="alert">{{ $errors->first() }}</div>
            @endif

            <form class="nd-auth__form" method="POST" action="{{ route('admin.login.attempt') }}">
                @csrf
                <div class="nd-field">
                    <label for="email">이메일</label>
                    <input class="nd-input" id="email" name="email" type="email" value="{{ old('email') }}"
                           autocomplete="username" required autofocus>
                </div>
                <div class="nd-field">
                    <label for="password">비밀번호</label>
                    <input class="nd-input" id="password" name="password" type="password"
                           autocomplete="current-password" required>
                </div>

                <label class="nd-check" style="margin-bottom:22px">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    로그인 상태 유지
                </label>

                <button type="submit" class="nd-btn nd-btn--ink nd-btn--block">로그인</button>
            </form>

            <p class="nd-auth__foot">
                시청·농가·해외협력사는 <a href="{{ route('portal.login') }}">협력 포털</a>로 로그인하세요.
            </p>

            @if (config('ndn.show_test_logins'))
                <div class="nd-demo">
                    <div class="nd-demo__h">테스트 계정 · 클릭하면 자동 입력</div>
                    <ul>
                        @foreach ([
                            ['admin@ndn.test', 'NDN 관리자', true],
                            ['city@ndn.test', '시청 담당자', false],
                            ['farm@ndn.test', '농가', false],
                            ['agency@ndn.test', '송출기관', false],
                            ['partner@ndn.test', '제휴 대리점', false],
                        ] as [$acctEmail, $acctLabel, $acctIsAdmin])
                            <li>
                                <button type="button" data-nd-fill data-email="{{ $acctEmail }}" data-pw="password">
                                    <span class="nd-demo__r">{{ $acctLabel }}@if ($acctIsAdmin) <em>★ 콘솔 접근</em>@endif</span>
                                    <span class="nd-demo__e">{{ $acctEmail }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="nd-demo__f">비밀번호 공통 <code>password</code> · 콘솔은 ★관리자만 접근</div>
                </div>
                <script>
                    document.querySelectorAll('[data-nd-fill]').forEach(function (b) {
                        b.addEventListener('click', function () {
                            document.getElementById('email').value = b.dataset.email;
                            document.getElementById('password').value = b.dataset.pw;
                            document.getElementById('password').focus();
                        });
                    });
                </script>
            @endif
        </div>
    </main>
</div>
</body>
</html>
