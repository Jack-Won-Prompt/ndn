<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B0F16">
<title>협력 포털 로그인 — N.D.N Korea</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
<link rel="stylesheet" href="{{ asset('site/assets/css/ndn.css') }}?v={{ @filemtime(public_path('site/assets/css/ndn.css')) }}">
</head>
<body>
<div class="nd-auth">

    <aside class="nd-auth__side">
        @include('partials.logo', ['on' => 'ink', 'href' => url('/'), 'sub' => 'Korea'])
        <h2 class="nd-h1">협력 포털</h2>
        <p>
            시청 · 농가 · 해외협력사 · 제휴 대리점이 각자 맡은 일을 같은 데이터 위에서 처리합니다.
            수요 신청, 배정 확인, 정착 서비스 처리를 이곳에서 합니다.
        </p>
    </aside>

    <main class="nd-auth__main">
        <div class="nd-auth__box">
            <h1 class="nd-h2">로그인</h1>
            <p>시청·농가·해외협력사 계정으로 로그인하세요.</p>

            @if ($errors->any())
                <div class="nd-note nd-note--err" style="margin-top:20px" role="alert">{{ $errors->first() }}</div>
            @endif

            <form class="nd-auth__form" method="POST" action="{{ route('portal.login.attempt') }}">
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
                NDN 관리자는 <a href="{{ url('admin/login') }}">운영 콘솔</a>로 로그인하세요.
            </p>

            @if (config('ndn.show_test_logins'))
                <div class="nd-demo">
                    <div class="nd-demo__h">테스트 계정 · 클릭하면 자동 입력</div>
                    <ul>
                        @foreach ([
                            ['city@ndn.test', '시청 담당자'],
                            ['farm@ndn.test', '농가'],
                            ['agency@ndn.test', '해외 협력사(송출기관)'],
                            ['partner@ndn.test', '제휴 대리점'],
                        ] as [$acctEmail, $acctLabel])
                            <li>
                                <button type="button" data-nd-fill data-email="{{ $acctEmail }}" data-pw="password">
                                    <span class="nd-demo__r">{{ $acctLabel }}</span>
                                    <span class="nd-demo__e">{{ $acctEmail }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="nd-demo__f">비밀번호 공통 <code>password</code></div>
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
