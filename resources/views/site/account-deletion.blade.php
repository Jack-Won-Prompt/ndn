@extends('site.layout')

@section('title', '계정 삭제 요청 — N.D.N Korea')
@section('description', 'N.D.N Korea 계정 및 데이터 삭제를 요청합니다.')

@php
    use App\Models\Setting;
    $email = Setting::get('company.email', 'privacy@ndnkorea.co.kr');
@endphp

@section('content')
    <section class="page-head">
        <div class="wrap page-head__inner">
            <p class="crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>계정 삭제 요청</p>
            <h1>계정 및 데이터 삭제 요청</h1>
            <p>N.D.N Korea 앱 계정과 관련 데이터의 삭제를 요청할 수 있습니다.</p>
        </div>
    </section>

    <section class="section">
        <div class="wrap legal">
            @if (session('deletion_ok'))
                <div class="del-ok">
                    ✅ 삭제 요청이 접수되었습니다. 확인 후 처리되며, 필요 시 기재하신 이메일로 안내드립니다.
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

            <form class="del-form" method="POST" action="{{ route('legal.account-deletion.store') }}">
                @csrf
                <div class="del-field">
                    <label for="del-name">이름 <span class="req">*</span></label>
                    <input id="del-name" type="text" name="name" value="{{ old('name') }}" maxlength="100" required>
                    @error('name')<p class="del-err">{{ $message }}</p>@enderror
                </div>
                <div class="del-field">
                    <label for="del-email">가입 이메일(로그인 ID) <span class="req">*</span></label>
                    <input id="del-email" type="email" name="email" value="{{ old('email') }}" maxlength="150" required>
                    @error('email')<p class="del-err">{{ $message }}</p>@enderror
                </div>
                <div class="del-field">
                    <label for="del-reason">사유 (선택)</label>
                    <textarea id="del-reason" name="reason" rows="3" maxlength="1000">{{ old('reason') }}</textarea>
                    @error('reason')<p class="del-err">{{ $message }}</p>@enderror
                </div>
                <label class="del-consent">
                    <input type="checkbox" name="confirm" value="1" {{ old('confirm') ? 'checked' : '' }}>
                    <span>위 안내(삭제 항목·90일 후 파기)를 확인했으며 계정 삭제를 요청합니다.</span>
                </label>
                @error('confirm')<p class="del-err">{{ $message }}</p>@enderror
                <div class="del-actions">
                    <button type="submit" class="del-btn">삭제 요청 보내기</button>
                </div>
            </form>
        </div>
    </section>

    <style>
        .legal{max-width:760px;}
        .legal h2{font-size:20px;margin:28px 0 10px;}
        .legal p{line-height:1.75;color:#333A44;margin:8px 0;}
        .legal ul{margin:8px 0 8px 2px;padding-left:18px;line-height:1.8;color:#333A44;}
        .legal a{color:#1E9C92;font-weight:600;}
        .del-ok{background:#E7F3F1;border:1px solid #B9E0D9;color:#12695F;padding:14px 16px;border-radius:10px;margin-bottom:22px;font-size:15px;}
        .del-form{margin-top:14px;background:#fff;border:1px solid #E3E6EA;border-radius:14px;padding:22px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .del-field{margin-bottom:16px;}
        .del-field label{display:block;font-size:14px;font-weight:700;color:#1B1E24;margin-bottom:6px;}
        .del-field input, .del-field textarea{width:100%;box-sizing:border-box;border:1px solid #D4DCDB;border-radius:9px;padding:10px 12px;font-family:inherit;font-size:15px;}
        .del-field input:focus, .del-field textarea:focus{outline:none;border-color:#1E9C92;box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        .req{color:#E5484D;}
        .del-consent{display:flex;gap:9px;align-items:flex-start;font-size:14px;color:#333A44;line-height:1.55;cursor:pointer;}
        .del-consent input{margin-top:3px;}
        .del-err{color:#B42318;font-size:13px;margin:6px 0 0;}
        .del-actions{margin-top:18px;}
        .del-btn{font-family:inherit;font-size:15px;font-weight:700;color:#fff;background:#E5484D;border:0;border-radius:10px;padding:12px 22px;cursor:pointer;}
        .del-btn:hover{background:#C93B40;}
    </style>
@endsection
