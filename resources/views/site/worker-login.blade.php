@extends('site.layout')

@section('title', '근로자 로그인 — N.D.N Korea')

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>근로자 로그인</p>
            <h1 class="nd-h1">근로자 로그인</h1>
            <p class="nd-lead">합격하신 분은 여기서 근무지와 본인 정보를 확인할 수 있습니다.</p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow">
            @if (session('status'))
                <div class="nd-note nd-note--ok" style="margin-bottom:22px" role="status">{{ session('status') }}</div>
            @endif

            <form class="nd-panel" method="POST" action="{{ route('worker.login.attempt') }}">
                @csrf

                <div class="nd-field">
                    <label for="wl-email">이메일 <span class="nd-req">*</span></label>
                    <input class="nd-input @error('email') is-bad @enderror" id="wl-email" type="email"
                           name="email" value="{{ old('email') }}" required autocomplete="username">
                    @error('email')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <div class="nd-field">
                    <label for="wl-password">비밀번호 <span class="nd-req">*</span></label>
                    <input class="nd-input @error('password') is-bad @enderror" id="wl-password" type="password"
                           name="password" required autocomplete="current-password">
                    @error('password')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <label class="nd-check">
                    <input type="checkbox" name="remember" value="1"> 로그인 상태 유지
                </label>

                <div class="nd-btnrow" style="margin-top:22px">
                    <button class="nd-btn nd-btn--accent" type="submit">로그인</button>
                    <a class="nd-btn" href="{{ route('worker.password.request') }}">비밀번호를 잊으셨나요?</a>
                </div>

                <p class="nd-help" style="margin-top:18px">
                    아직 신청하지 않으셨나요? <a href="{{ route('site.apply') }}">지원하기</a>
                </p>
            </form>
        </div>
    </section>
@endsection
