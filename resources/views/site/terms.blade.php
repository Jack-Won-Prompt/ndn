@extends('site.layout')

@section('title', '이용약관 — N.D.N Korea')
@section('description', '주식회사 앤디앤(N.D.N Korea) 서비스 이용약관.')

@php
    use App\Models\Setting;
    $email = Setting::get('company.email', 'support@ndnkorea.co.kr');
@endphp

@section('content')
    <section class="nd-pagehero">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>이용약관</p>
            <h1 class="nd-h1">이용약관</h1>
            <p class="nd-lead">N.D.N Korea 서비스 이용에 관한 회사와 이용자 간의 권리·의무를 정합니다.</p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap nd-wrap--narrow nd-prose">
            <p class="nd-prose__meta">시행일: 2026년 7월 28일</p>

            <h2>제1조 (목적)</h2>
            <p>이 약관은 주식회사 앤디앤(이하 "회사")이 제공하는 외국인 계절근로자(E-8) 통합관리 플랫폼 및 모바일 앱(이하 "서비스")의 이용과 관련하여 회사와 이용자의 권리·의무 및 책임사항을 규정함을 목적으로 합니다.</p>

            <h2>제2조 (정의)</h2>
            <ul>
                <li>"이용자"란 이 약관에 따라 서비스를 이용하는 근로자·농가·지자체·송출기관·제휴 대리점·관리자를 말합니다.</li>
                <li>"계정"이란 이용자 식별과 서비스 이용을 위해 부여되는 이메일 및 비밀번호를 말합니다.</li>
            </ul>

            <h2>제3조 (약관의 게시와 개정)</h2>
            <ul>
                <li>회사는 이 약관을 서비스 초기 화면 또는 연결 화면에 게시합니다.</li>
                <li>회사는 관련 법령을 위반하지 않는 범위에서 약관을 개정할 수 있으며, 개정 시 시행일과 사유를 서비스 내 공지합니다.</li>
            </ul>

            <h2>제4조 (서비스의 제공 및 변경)</h2>
            <ul>
                <li>회사는 모집·매칭·입국·정착·사후관리 등 서비스를 제공합니다.</li>
                <li>회사는 운영·기술상 필요에 따라 서비스의 내용을 변경하거나 중단할 수 있으며, 중요한 변경은 사전에 공지합니다.</li>
            </ul>

            <h2>제5조 (이용계약의 성립 및 계정)</h2>
            <ul>
                <li>근로자는 앱에서 회원가입을 신청하고, <b>회사(관리자)의 승인</b>으로 이용계약이 성립됩니다.</li>
                <li>이용자는 계정 정보를 정확히 제공해야 하며, 계정의 관리 책임은 이용자에게 있습니다.</li>
                <li>타인의 명의·정보를 도용하여 가입할 수 없습니다.</li>
            </ul>

            <h2>제6조 (이용자의 의무 및 금지행위)</h2>
            <ul>
                <li>이용자는 관련 법령과 이 약관, 회사의 안내를 준수해야 합니다.</li>
                <li>다음 행위를 금지합니다: 허위 정보 등록, 타인의 정보 도용, 서비스 운영 방해, 부정한 방법의 접근, 법령·공서양속에 반하는 행위.</li>
            </ul>

            <h2>제7조 (서비스 이용의 제한)</h2>
            <p>이용자가 이 약관 또는 법령을 위반한 경우, 회사는 사전 통지 후(긴급 시 사후 통지) 서비스 이용을 제한하거나 계약을 해지할 수 있습니다.</p>

            <h2>제8조 (게시물 및 콘텐츠)</h2>
            <p>이용자가 서비스에 입력·제출한 정보에 대한 책임은 이용자에게 있으며, 회사는 서비스 제공 목적 범위에서 이를 이용합니다. 개인정보의 처리는 <a href="{{ route('site.privacy') }}">개인정보처리방침</a>을 따릅니다.</p>

            <h2>제9조 (개인정보의 보호)</h2>
            <p>회사는 관련 법령에 따라 이용자의 개인정보를 보호하며, 자세한 내용은 <a href="{{ route('site.privacy') }}">개인정보처리방침</a>에서 정합니다. 계정·데이터 삭제는 <a href="{{ route('legal.account-deletion') }}">계정 삭제 요청</a>을 통해 신청할 수 있습니다.</p>

            <h2>제10조 (면책)</h2>
            <ul>
                <li>천재지변, 통신 장애 등 회사의 귀책이 아닌 사유로 인한 손해에 대해 회사는 책임을 지지 않습니다.</li>
                <li>회사는 이용자가 서비스를 통해 게시·제공한 정보의 신뢰성·정확성에 대해 보증하지 않습니다.</li>
            </ul>

            <h2>제11조 (준거법 및 관할)</h2>
            <p>이 약관은 대한민국 법령에 따라 해석되며, 서비스 이용과 관련한 분쟁은 관계 법령이 정한 절차와 관할 법원에 따릅니다.</p>

            <h2>부칙</h2>
            <p>이 약관은 2026년 7월 28일부터 시행합니다. 문의: {{ $email }}</p>
        </div>
    </section>

    <style>
        .legal{max-width:820px;}
        .legal__meta{color:#6B7280;font-size:14px;margin:0 0 24px;}
        .legal h2{font-size:20px;margin:30px 0 10px;padding-top:6px;}
        .legal p{line-height:1.75;color:#333A44;margin:8px 0;}
        .legal ul{margin:8px 0 8px 2px;padding-left:18px;line-height:1.8;color:#333A44;}
        .legal li{margin:4px 0;}
        .legal a{color:#1E9C92;font-weight:600;}
    </style>
@endsection
