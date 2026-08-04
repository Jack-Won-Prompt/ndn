@extends('site.layout')

@section('title', '계정 삭제 요청 — N.D.N Korea')
@section('description', 'N.D.N Korea 계정 및 데이터 삭제를 요청합니다.')

@php
    use App\Models\Setting;
    $email = Setting::get('company.email', 'privacy@ndnkorea.co.kr');
@endphp

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>계정 삭제 요청</p>
            <h1 class="nd-h1">계정 및 데이터 삭제 요청</h1>
            <p class="nd-lead">N.D.N Korea 앱 계정과 관련 데이터의 삭제를 요청할 수 있습니다.</p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow nd-prose">
            @if (session('deletion_ok'))
                <div class="nd-note nd-note--ok" style="margin-bottom:26px" role="status">
                    삭제 요청이 접수되었습니다. 확인 후 처리되며, 필요 시 기재하신 이메일로 안내드립니다.
                </div>
            @endif

            <h2>삭제되는 데이터</h2>
            <ul>
                <li>계정 정보(이름·이메일·비밀번호·국적·언어)</li>
                <li>신원·정착 서비스 정보(여권번호·생년월일·전화·주소·전자서명 등 민감정보)</li>
                <li>온보딩 제출 정보, 월별 자가평가, 민원·채팅 내용, 앱 푸시 토큰</li>
            </ul>

            <h2>보관되는 데이터(예외)</h2>
            <p>관계 법령상 보존 의무가 있는 기록은 해당 <b>법정 보존 기간</b> 동안 분리 보관 후 파기됩니다(그 외 목적에는 사용하지 않습니다).</p>

            <h2>처리 절차 및 기간</h2>
            <ul>
                <li>요청 접수 → 본인 확인 → 계정 비활성화(soft delete)</li>
                <li>비활성화 후 <b>90일 경과 시 민감 개인정보를 파기(삭제)</b>합니다(§7-7).</li>
                <li>통상 영업일 기준 수일 내 처리되며, 본인 확인이 필요한 경우 추가 연락드릴 수 있습니다.</li>
            </ul>

            <h2>요청 방법</h2>
            <p>아래 양식으로 신청하거나, 이메일(<a href="mailto:{{ $email }}">{{ $email }}</a>)로 삭제를 요청할 수 있습니다.</p>

            <form class="nd-panel" style="margin-top:22px" method="POST" action="{{ route('legal.account-deletion.store') }}">
                @csrf
                <div class="nd-field">
                    <label for="del-name">이름 <span class="nd-req">*</span></label>
                    <input class="nd-input @error('name') is-bad @enderror" id="del-name" type="text"
                           name="name" value="{{ old('name') }}" maxlength="100" required>
                    @error('name')<p class="nd-err">{{ $message }}</p>@enderror
                </div>
                <div class="nd-field">
                    <label for="del-email">가입 이메일(로그인 ID) <span class="nd-req">*</span></label>
                    <input class="nd-input @error('email') is-bad @enderror" id="del-email" type="email"
                           name="email" value="{{ old('email') }}" maxlength="150" required>
                    @error('email')<p class="nd-err">{{ $message }}</p>@enderror
                </div>
                <div class="nd-field">
                    <label for="del-reason">사유 (선택)</label>
                    <textarea class="nd-textarea @error('reason') is-bad @enderror" id="del-reason"
                              name="reason" rows="3" maxlength="1000">{{ old('reason') }}</textarea>
                    @error('reason')<p class="nd-err">{{ $message }}</p>@enderror
                </div>

                <label class="nd-check" style="align-items:flex-start;margin-bottom:6px">
                    <input type="checkbox" name="confirm" value="1" {{ old('confirm') ? 'checked' : '' }} style="margin-top:3px">
                    <span style="color:var(--nd-text-2);line-height:1.6">위 안내(삭제 항목·90일 후 파기)를 확인했으며 계정 삭제를 요청합니다.</span>
                </label>
                @error('confirm')<p class="nd-err">{{ $message }}</p>@enderror

                <div class="nd-btnrow" style="margin-top:20px">
                    <button type="submit" class="nd-btn nd-btn--accent">삭제 요청 보내기</button>
                </div>
            </form>
        </div>
    </section>
@endsection
