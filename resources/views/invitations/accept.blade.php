<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0B0F16">
<title>계정 설정 — N.D.N Korea 협력 포털</title>
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
<link rel="stylesheet" href="{{ asset('site/assets/css/ndn.css') }}?v={{ @filemtime(public_path('site/assets/css/ndn.css')) }}">
</head>
<body>
<div class="nd-auth">

    <aside class="nd-auth__side">
        <a class="nd-logo" href="{{ url('/') }}">
            <span class="nd-logo__mark" style="color:#fff">N<i>.</i>D<i>.</i>N</span>
            <span class="nd-logo__sub">Korea</span>
        </a>
        <h2 class="nd-h1">협력 포털에<br>초대되었습니다</h2>
        <p>
            비밀번호를 설정하면 바로 이용할 수 있습니다.
            이 링크는 만료 기한이 지나면 사용할 수 없습니다.
        </p>
    </aside>

    <main class="nd-auth__main">
        <div class="nd-auth__box">
            <h1 class="nd-h2">계정 설정</h1>
            <p>초대를 수락하고 로그인 비밀번호를 설정하세요.</p>

            <span class="nd-badge nd-badge--mute" style="margin-top:16px">{{ $roleLabel }}</span>

            @if ($errors->any())
                <div class="nd-note nd-note--err" style="margin-top:18px" role="alert">
                    <ul style="margin:0;padding-left:16px;list-style:disc">
                        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            <form class="nd-auth__form" method="POST" action="{{ route('invite.accept', ['token' => $token]) }}">
                @csrf
                <div class="nd-field">
                    <label for="inv-email">이메일</label>
                    <input class="nd-input" id="inv-email" type="email" value="{{ $email }}" readonly
                           style="background:var(--nd-paper-2);color:var(--nd-text-2)">
                </div>
                <div class="nd-field">
                    <label for="inv-name">이름</label>
                    <input class="nd-input" id="inv-name" type="text" name="name" value="{{ old('name', $name) }}"
                           placeholder="담당자 이름" required autofocus>
                </div>
                <div class="nd-field">
                    <label for="inv-pw">비밀번호 (8자 이상)</label>
                    <input class="nd-input" id="inv-pw" type="password" name="password"
                           placeholder="새 비밀번호" autocomplete="new-password" required>
                </div>
                <div class="nd-field">
                    <label for="inv-pw2">비밀번호 확인</label>
                    <input class="nd-input" id="inv-pw2" type="password" name="password_confirmation"
                           placeholder="비밀번호 확인" autocomplete="new-password" required>
                </div>

                <button type="submit" class="nd-btn nd-btn--ink nd-btn--block" style="margin-top:6px">계정 만들기</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>
