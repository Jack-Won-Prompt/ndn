@extends('site.layout')

@section('title', '근로자 지원하기 — N.D.N Korea')
@section('description', '외국인 계절근로자(E-8) 지원 신청. 정보를 입력하고 서류를 함께 올릴 수 있습니다.')

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>지원하기</p>
            <h1 class="nd-h1">근로자 지원하기</h1>
            <p class="nd-lead">
                아래 내용을 채워 신청하면 담당자가 확인합니다. 확인이 끝나면 결과를 알려 드립니다.
                이미 신청하셨다면 <a href="{{ route('worker.login') }}">로그인</a>해서 진행 상황을 볼 수 있습니다.
            </p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow">
            @if ($errors->any())
                <div class="nd-note nd-note--err" style="margin-bottom:22px" role="alert">
                    입력한 내용을 다시 확인해 주세요.
                </div>
            @endif

            <form class="nd-panel" method="POST" action="{{ route('site.apply.store') }}" enctype="multipart/form-data">
                @csrf

                <h2 class="nd-panel__title">본인 정보</h2>

                <div class="nd-field">
                    <label for="ap-email">이메일 (로그인 아이디) <span class="nd-req">*</span></label>
                    <input class="nd-input @error('email') is-bad @enderror" id="ap-email" type="email"
                           name="email" value="{{ old('email') }}" maxlength="255" required>
                    <p class="nd-help">결과 안내와 비밀번호 찾기에 쓰입니다. 본인이 열 수 있는 주소를 적어 주세요.</p>
                    @error('email')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <div class="nd-field">
                    <label for="ap-password">비밀번호 <span class="nd-req">*</span></label>
                    <input class="nd-input @error('password') is-bad @enderror" id="ap-password" type="password"
                           name="password" minlength="8" required autocomplete="new-password">
                    <p class="nd-help">8자 이상.</p>
                    @error('password')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <div class="nd-field">
                    <label for="ap-password2">비밀번호 확인 <span class="nd-req">*</span></label>
                    <input class="nd-input" id="ap-password2" type="password"
                           name="password_confirmation" minlength="8" required autocomplete="new-password">
                </div>

                @include('site._apply-fields', ['mode' => 'apply', 'prefill' => []])

                <h2 class="nd-panel__title" style="margin-top:34px">서류 (선택)</h2>
                <p class="nd-help" style="margin-bottom:12px">
                    아래 서류를 함께 올리면 확인이 빨라집니다. <b>지금 없어도 신청할 수 있습니다</b> —
                    부족하면 담당자가 이메일로 다시 요청합니다.
                </p>
                <ul class="nd-doclist">
                    @foreach ($expected as $doc)<li>{{ $doc }}</li>@endforeach
                </ul>

                <div class="nd-field">
                    <label for="ap-docs">파일 선택 (여러 개 가능)</label>
                    <input class="nd-input @error('documents.*') is-bad @enderror" id="ap-docs" type="file"
                           name="documents[]" multiple accept=".{{ str_replace(',', ',.', $mimes) }}">
                    <p class="nd-help">
                        최대 {{ $maxFiles }}개 · 하나당 {{ round($maxKb / 1024) }}MB 이하 ·
                        {{ strtoupper(str_replace(',', ', ', $mimes)) }}
                    </p>
                    @error('documents')<p class="nd-err">{{ $message }}</p>@enderror
                    @error('documents.*')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <div class="nd-note" style="margin-top:22px">
                    적어 주신 여권번호·생년월일·연락처는 <b>암호화해서 보관</b>하며, 계절근로자 선발과 배치
                    목적으로만 씁니다. 위치는 수집하지 않습니다.
                    <a href="{{ route('site.privacy') }}">개인정보처리방침</a>
                </div>

                <div class="nd-btnrow" style="margin-top:22px">
                    <button class="nd-btn nd-btn--accent" type="submit">신청하기</button>
                    <a class="nd-btn" href="{{ route('worker.login') }}">이미 신청했어요 (로그인)</a>
                </div>
            </form>
        </div>
    </section>
@endsection
