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
                    <h2>함께 성장하는 인력교류 플랫폼</h2>
                    <p>
                        안녕하십니까. 급변하는 글로벌 환경 속에서 대한민국 농업과 어업 현장은
                        심각한 인력 부족 문제에 직면해 있습니다. 이에 주식회사 앤디앤(NDN Co., Ltd.)은
                        단순한 인력 공급을 넘어, 외국인 근로자와 대한민국 농·어업 현장이 함께 성장할 수 있는
                        지속가능한 인력교류 플랫폼을 구축하고자 설립되었습니다.
                    </p>
                    <p>
                        당사는 외국인 계절근로자(E-8) 사업을 중심으로 해외 인재 발굴, 한국어 및 문화 교육,
                        입국 후 생활관리, 근로환경 지원, 귀국 후 사후관리까지 체계적인 운영 시스템을
                        구축하고 있습니다. 근로자와 고용주 모두가 만족하는 상생 모델로 대한민국 농촌의
                        경쟁력 강화에 기여하고자 노력하고 있습니다.
                    </p>
                    <p>
                        또한 해외 정부기관·지방자치단체·교육기관 및 협력기관과의 긴밀한 네트워크를 통해
                        투명하고 신뢰할 수 있는 국제 인력교류 체계를 만들어가고 있습니다.
                    </p>
                    <p style="margin-top:22px;font-weight:700;color:var(--ink)">주식회사 앤디앤 대표이사 전병언</p>
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

                    <div class="timeline">
                        <div class="tl-item">
                            <span class="tl-item__year">2024</span>
                            <h3>주식회사 앤디앤(NDN Co., Ltd.) 설립</h3>
                            <p>외국인 계절근로자 관리·교육 사업 개시, 농업분야 외국인 인력관리 시스템 구축</p>
                        </div>
                        <div class="tl-item">
                            <span class="tl-item__year">2025</span>
                            <h3>송출국 협력체계 구축 및 MOU 체결</h3>
                            <p>방글라데시 현지 NDN 교육서비스센터 설립, 방글라데시 노동국 산하 국영 송출기업 보이셀(BOESL)·중국 협력기관과 MOU, 당진시 외국인근로자 교육·행정서비스 지원 구축</p>
                        </div>
                        <div class="tl-item">
                            <span class="tl-item__year">2025.09</span>
                            <h3>계절근로자 공급 MOU</h3>
                            <p>당진시 주체·NDN 주관, 방글라데시 노동국 계절근로자 공급 협약 체결, 안전교육 프로그램 운영</p>
                        </div>
                        <div class="tl-item">
                            <span class="tl-item__year">2026</span>
                            <h3>당진시 농가 계절근로자 공급 서비스 개시</h3>
                            <p>현장 모니터링 시스템 운영, 당진시 교육프로그램 참여업체 선정, 지자체·농가 협력사업 확대(당진시 헬프 지원센터 설립)</p>
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
            </div>
            <div class="table-scroll">
                <table class="tbl">
                    <caption class="sr-only">N.D.N Korea 사업자 정보</caption>
                    <tbody>
                        <tr><td>법인명</td><td>주식회사 앤디앤 (NDN Co., Ltd.)</td></tr>
                        <tr><td>대표이사</td><td>{{ $S['company.ceo'] ?? '전병언' }}</td></tr>
                        <tr><td>사업자등록번호</td><td>{{ $S['company.biz_no'] ?? '771-88-02980' }}</td></tr>
                        <tr><td>법인등록번호</td><td>110111-8845442</td></tr>
                        <tr><td>개업연월일</td><td>2024년 1월 1일</td></tr>
                        <tr><td>소재지</td><td>{{ $S['company.address'] ?? '경기도 김포시 양촌읍 대곶남로580번길 55, 가동' }}</td></tr>
                        <tr><td>업태 · 종목</td><td>정보통신업 / 응용 소프트웨어 개발 및 공급업 외</td></tr>
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
