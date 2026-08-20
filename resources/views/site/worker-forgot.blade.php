@extends('site.layout')

@section('title', '비밀번호 찾기 — N.D.N Korea')

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('worker.login') }}">근로자 로그인</a><span>›</span>비밀번호 찾기</p>
            <h1 class="nd-h1">비밀번호 찾기</h1>
            <p class="nd-lead">가입할 때 쓰신 이메일 주소로 재설정 링크를 보내 드립니다.</p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow">
            @if (session('status'))
                <div class="nd-note nd-note--ok" style="margin-bottom:22px" role="status">{{ session('status') }}</div>
            @endif

            <form class="nd-panel" method="POST" action="{{ route('worker.password.email') }}">
                @csrf

                <div class="nd-field">
                    <label for="wf-email">이메일 <span class="nd-req">*</span></label>
                    <input class="nd-input @error('email') is-bad @enderror" id="wf-email" type="email"
                           name="email" value="{{ old('email') }}" required autocomplete="username">
                    @error('email')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <div class="nd-btnrow" style="margin-top:22px">
                    <button class="nd-btn nd-btn--accent" type="submit">재설정 링크 받기</button>
                    <a class="nd-btn" href="{{ route('worker.login') }}">돌아가기</a>
                </div>

                <p class="nd-help" style="margin-top:18px">
                    메일이 오지 않으면 스팸함을 확인해 주세요. 그래도 없으면
                    <a href="{{ route('site.contact') }}">문의하기</a>로 알려 주세요.
                </p>
            </form>
        </div>
    </section>
@endsection
