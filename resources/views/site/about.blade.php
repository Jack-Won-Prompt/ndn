@extends('site.layout')

@section('title', '회사소개 — N.D.N Korea')
@section('description', '주식회사 앤디앤(N.D.N Korea)의 비전과 연혁, 협력 네트워크를 소개합니다.')

@section('content')


    <section class="page-head">
        <img class="page-head__bg" src="{{ asset('site/assets/img/landscape.jpg') }}" alt="">
        <div class="wrap page-head__inner">
            <p class="crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>회사소개</p>
            <h1>회사소개</h1>
            <p>제도와 현장 사이에서, 양쪽 말을 모두 알아듣는 회사가 필요했습니다.</p>
        </div>
    </section>

    <!-- ============ 대표 인사말 ============ -->
    <section class="section">
        <div class="wrap">
            <div class="split">
                <div class="split__media">
                    <div class="photo photo--tall">
                        <img src="{{ asset('site/assets/img/handshake.jpg') }}" alt="악수하는 두 사람">
                    </div>
                </div>
                <div>
                    <span class="eyebrow">Message</span>
                    <div class="rule"></div>
                    <h2>사람이 오는 일입니다</h2>
                    <p>
                        계절근로자 사업은 인원 수로 이야기되지만, 실제로는 한 사람씩 오는 일입니다.
                        비행기에서 내린 사람에게 통장이 없고, 전화가 안 되고, 말이 안 통하면
                        그 사람의 첫 달은 그대로 버려집니다.
                    </p>
                    <p>
                        저희는 그 첫 달을 줄이는 일부터 시작했습니다.
                        입국 전에 미리 채울 수 있는 정보는 미리 채우고,
                        입국 첫 주에 몰리는 일은 미리 예약해 둡니다.
                    </p>
                    <p>
                        농가에는 예측 가능한 일정을, 지자체에는 근거가 남는 기록을,
                        근로자에게는 자기 모국어로 된 안내를 드리는 것.
                        그것이 저희가 하는 일의 전부입니다.
                    </p>
                    <p style="margin-top:26px;font-size:13px;color:var(--gray-500)">
                        ⚠ 인사말 문안은 초안입니다. 대표이사 확인 후 확정하십시오.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 비전 / 미션 ============ -->
    <section class="section section--dark">
        <div class="wrap">
            <div class="sec-head sec-head--center">
                <span class="eyebrow">Vision</span>
                <div class="rule"></div>
                <h2>세 가지를 지킵니다</h2>
            </div>
            <div class="grid grid--3">
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/network.png') }}" alt=""></div>
                    <h3>Global</h3>
                    <p>송출국 기관과 직접 연결된 채널을 유지합니다. 중간 단계가 줄면 근로자가 부담하는 비용도 함께 줄어듭니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/admin.png') }}" alt=""></div>
                    <h3>Professional</h3>
                    <p>제도 변경과 서류 요건을 먼저 읽고 반영합니다. 담당자가 공문을 찾아 헤매지 않게 합니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/aftercare.png') }}" alt=""></div>
                    <h3>Trustworthy</h3>
                    <p>개인정보는 필요한 범위에서만 다룹니다. 누가 언제 무엇을 열람했는지 기록으로 남깁니다.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============ 연혁 ============ -->
    <section class="section">
        <div class="wrap">
            <div class="split">
                <div>
                    <span class="eyebrow">History</span>
                    <div class="rule"></div>
                    <h2>연혁</h2>
                    <p style="margin-bottom:34px">
                        ⚠ 아래 항목은 전부 자리표시자입니다.
                        실제 설립일·협약일·사업 개시일로 교체하기 전까지 공개하지 마십시오.
                    </p>

                    <div class="timeline">
                        <div class="tl-item">
                            <span class="tl-item__year">○○○○</span>
                            <h3>주식회사 앤디앤 설립</h3>
                            <p>외국인 인력 도입 지원 사업 개시</p>
                        </div>
                        <div class="tl-item">
                            <span class="tl-item__year">○○○○</span>
                            <h3>송출국 기관 업무협약</h3>
                            <p>현지 모집·면접 체계 구축</p>
                        </div>
                        <div class="tl-item">
                            <span class="tl-item__year">○○○○</span>
                            <h3>지자체 협약 체결</h3>
                            <p>계절근로자 프로그램 운영 참여</p>
                        </div>
                        <div class="tl-item">
                            <span class="tl-item__year">○○○○</span>
                            <h3>통합관리 플랫폼 가동</h3>
                            <p>수요 신청부터 사후관리까지 단일 시스템 전환</p>
                        </div>
                    </div>
                </div>
                <div class="split__media">
                    <div class="photo photo--tall">
                        <img src="{{ asset('site/assets/img/mou_signing.jpg') }}" alt="협약 서명 장면">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 사업자 정보 ============ -->
    <section class="section section--gray">
        <div class="wrap">
            <div class="sec-head">
                <span class="eyebrow">Company</span>
                <div class="rule"></div>
                <h2>사업자 정보</h2>
                <p>⚠ 전 항목 미확정입니다. 등기부·사업자등록증 기준으로 채우십시오.</p>
            </div>
            <div class="table-scroll">
                <table class="tbl">
                    <caption class="sr-only">N.D.N Korea 사업자 정보</caption>
                    <tbody>
                        <tr><td>법인명</td><td>주식회사 앤디앤 (N.D.N Co., Ltd.)</td></tr>
                        <tr><td>대표이사</td><td>{{ $S['company.ceo'] ?? '○○○' }}</td></tr>
                        <tr><td>사업자등록번호</td><td>{{ $S['company.biz_no'] ?? '○○○-○○-○○○○○' }}</td></tr>
                        <tr><td>주소</td><td>{{ $S['company.address'] ?? '○○도 ○○시 ○○로 ○○' }}</td></tr>
                        <tr><td>대표전화</td><td>{{ $S['company.phone'] ?? '○○-○○○○-○○○○' }}</td></tr>
                        <tr><td>이메일</td><td>{{ $S['company.email'] ?? '○○○@○○○.co.kr' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="wrap">
            <h2>협업을 논의하고 싶으신가요</h2>
            <p>지자체, 농협, 송출기관 모두 환영합니다.</p>
            <div class="btn-row">
                <a class="btn btn--light" href="{{ route('site.contact') }}">문의하기</a>
                <a class="btn btn--ghost" href="{{ route('site.partners') }}">협력기관 보기</a>
            </div>
        </div>
    </section>
@endsection
