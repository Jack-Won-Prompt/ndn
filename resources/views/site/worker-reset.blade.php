@extends('site.layout')

@section('title', '새 비밀번호 설정 — N.D.N Korea')

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <h1 class="nd-h1">새 비밀번호 설정</h1>
            <p class="nd-lead">새로 쓸 비밀번호를 정해 주세요.</p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow">
            <form class="nd-panel" method="POST" action="{{ route('worker.password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="nd-field">
                    <label for="wr-email">이메일 <span class="nd-req">*</span></label>
                    <input class="nd-input @error('email') is-bad @enderror" id="wr-email" type="email"
                           name="email" value="{{ old('email', $email) }}" required autocomplete="username">
                    @error('email')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <div class="nd-field">
                    <label for="wr-password">새 비밀번호 <span class="nd-req">*</span></label>
                    <input class="nd-input @error('password') is-bad @enderror" id="wr-password" type="password"
                           name="password" minlength="8" required autocomplete="new-password">
                    <p class="nd-help">8자 이상.</p>
                    @error('password')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <div class="nd-field">
                    <label for="wr-password2">새 비밀번호 확인 <span class="nd-req">*</span></label>
                    <input class="nd-input" id="wr-password2" type="password"
                           name="password_confirmation" minlength="8" required autocomplete="new-password">
                </div>

                <div class="nd-btnrow" style="margin-top:22px">
                    <button class="nd-btn nd-btn--accent" type="submit">비밀번호 바꾸기</button>
                </div>
            </form>
        </div>
    </section>
@endsection
