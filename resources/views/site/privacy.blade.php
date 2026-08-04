@extends('site.layout')

@section('title', '개인정보처리방침 — N.D.N Korea')
@section('description', '주식회사 앤디앤(N.D.N Korea) 개인정보처리방침.')

@php
    use App\Models\Setting;
    $company = 'N.D.N Korea (주식회사 앤디앤)';
    $ceo = Setting::get('company.ceo', '—');
    $bizNo = Setting::get('company.biz_no', '—');
    $addr = Setting::get('company.address', '—');
    $phone = Setting::get('company.phone', '—');
    $email = Setting::get('company.email', 'privacy@ndnkorea.co.kr');
@endphp

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>개인정보처리방침</p>
            <h1 class="nd-h1">개인정보처리방침</h1>
            <p class="nd-lead">{{ $company }}(이하 "회사")는 이용자의 개인정보를 중요하게 생각하며 관련 법령을 준수합니다.</p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow nd-prose">
            <p class="nd-prose__meta">시행일: 2026년 7월 28일</p>

            <h2>1. 수집하는 개인정보 항목</h2>
            <p>회사는 서비스 제공을 위해 아래 항목을 수집합니다.</p>
            <ul>
                <li><b>회원가입·인증</b>: 이름, 이메일(로그인 ID), 비밀번호, 국적, 사용 언어</li>
                <li><b>신원·정착 서비스</b>: 여권번호, 생년월일, 본국 전화번호, 국내 주소, 비상 연락처, 전자서명, 계좌·보험·통신 신청 정보</li>
                <li><b>서비스 이용 과정</b>: 온보딩 제출 정보, 월별 자가평가, 민원·문의·채팅 내용</li>
                <li><b>기기·접속</b>: 앱 푸시 토큰(FCM), 접속 일시·기기 정보, 서비스 이용 기록</li>
                <li><b>위치정보</b>: <u>긴급 SOS를 누른 그 순간의 좌표 1회</u>, 점검자의 농가 방문 체크인 좌표(점검 증빙). 그 외 상시·주기적 위치는 수집하지 않습니다.</li>
            </ul>
            <p class="nd-prose__note">여권번호·생년월일·전화번호·계좌번호 등 민감정보는 <b>암호화하여 저장</b>합니다.</p>

            <h2>2. 개인정보의 수집 방법</h2>
            <ul>
                <li>모바일 앱·웹에서 이용자가 직접 입력·제출</li>
                <li>서비스 이용 과정에서 자동 생성·수집(접속 기록, 기기 토큰 등)</li>
            </ul>

            <h2>3. 개인정보의 이용 목적</h2>
            <ul>
                <li>회원 식별·인증, 계정 관리, 관리자 승인 처리</li>
                <li>외국인 계절근로자(E-8)의 모집·매칭·입국·정착·사후관리 등 서비스 제공</li>
                <li>정착 서비스(통장·보험·통신·유심) 신청 처리</li>
                <li>민원·문의 응대, 공지·알림 발송</li>
                <li>긴급 상황(SOS) 대응, 서비스 개선·통계</li>
            </ul>

            <h2>4. 보유·이용 기간 및 파기</h2>
            <p>개인정보는 수집·이용 목적이 달성되면 지체 없이 파기합니다. 다만 관계 법령에 따라 보존이 필요한 경우 해당 기간 동안 보관합니다.</p>
            <ul>
                <li>회원 탈퇴·삭제 요청 시: 계정을 비활성(soft delete) 처리하고, <b>90일 경과 후 민감 개인정보 필드를 파기(삭제)</b>합니다.</li>
                <li>법령상 보존 의무가 있는 기록(전자상거래·통신 등)은 해당 법정 기간 동안 보관 후 파기합니다.</li>
            </ul>

            <h2>5. 개인정보의 제3자 제공</h2>
            <p>회사는 이용자의 개인정보를 원칙적으로 외부에 제공하지 않습니다. 다만 아래의 경우 <b>이용자의 사전 동의를 받은 범위</b>에서 제공합니다.</p>
            <ul>
                <li><b>제휴 대리점(보험·통신 등)</b>: 정착 서비스 처리에 필요한 최소 정보. <u>이용자가 제3자 제공에 동의한 경우에 한하여</u> 배정·제공되며, 동의는 언제든지 철회할 수 있습니다.</li>
                <li>법령에 근거가 있거나 수사기관의 적법한 요청이 있는 경우</li>
            </ul>

            <h2>6. 처리의 위탁</h2>
            <p>회사는 원활한 서비스 제공을 위해 필요한 범위에서 처리를 위탁할 수 있으며, 위탁 시 관련 법령에 따라 안전하게 관리되도록 합니다(예: 클라우드 인프라, 푸시 알림 발송, 자동 번역).</p>

            <h2>7. 위치정보의 처리</h2>
            <p>회사는 이용자의 위치를 상시로 추적·저장하지 않습니다. 위치정보가 저장되는 경우는 다음 두 가지로 한정됩니다.</p>
            <ul>
                <li>이용자가 <b>긴급 SOS 버튼을 누른 그 순간</b> 전송된 1회 좌표(긴급 대응 목적)</li>
                <li>점검자가 농가를 <b>방문 체크인</b>한 좌표(점검 증빙 목적)</li>
            </ul>

            <h2>8. 이용자의 권리와 행사 방법</h2>
            <p>이용자는 언제든지 자신의 개인정보에 대해 열람·정정·삭제·처리정지·동의철회를 요청할 수 있습니다.</p>
            <ul>
                <li>앱 내 설정(동의 관리)에서 동의를 철회할 수 있습니다.</li>
                <li>계정·데이터 삭제는 <a href="{{ route('legal.account-deletion') }}">계정 삭제 요청 페이지</a>에서 신청하거나 아래 연락처로 요청할 수 있습니다.</li>
            </ul>

            <h2>9. 개인정보의 안전성 확보 조치</h2>
            <ul>
                <li>민감정보 암호화 저장, 전송 구간 암호화</li>
                <li>개인정보 접근 권한 최소화 및 접근 통제</li>
                <li>개인정보 접근·처리 내역에 대한 <b>접근 기록(감사 로그)</b> 보관</li>
                <li>외부 알림(문자·알림톡 등) 본문에 개인정보를 포함하지 않음</li>
            </ul>

            <h2>10. 자동 수집 장치 및 푸시 토큰</h2>
            <p>서비스는 로그인 유지·설정을 위해 최소한의 쿠키를 사용할 수 있으며, 앱 푸시 알림을 위해 기기 토큰(FCM)을 저장합니다. 앱 알림 설정에서 수신을 해제할 수 있습니다.</p>

            <h2>11. 개인정보 보호책임자 및 문의처</h2>
            <ul>
                <li>운영자: {{ $company }}</li>
                <li>대표자: {{ $ceo }}</li>
                <li>사업자등록번호: {{ $bizNo }}</li>
                <li>주소: {{ $addr }}</li>
                <li>연락처: {{ $phone }}</li>
                <li>이메일: {{ $email }}</li>
            </ul>

            <h2>12. 고지의 의무</h2>
            <p>이 개인정보처리방침의 내용 추가·삭제·수정이 있을 경우 시행 전 서비스 내 공지사항을 통해 고지합니다.</p>
        </div>
    </section>

    <style>
        .legal{max-width:820px;}
        .legal__meta{color:#6B7280;font-size:14px;margin:0 0 24px;}
        .legal__note{background:#F1F6F5;border-left:3px solid #1E9C92;padding:10px 14px;border-radius:6px;color:#12695F;font-size:14px;}
        .legal h2{font-size:20px;margin:30px 0 10px;padding-top:6px;}
        .legal p{line-height:1.75;color:#333A44;margin:8px 0;}
        .legal ul{margin:8px 0 8px 2px;padding-left:18px;line-height:1.8;color:#333A44;}
        .legal li{margin:4px 0;}
        .legal a{color:#1E9C92;font-weight:600;}
    </style>
@endsection
