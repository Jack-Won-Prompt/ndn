@extends('site.layout')

@section('title', '회사소개 — N.D.N Korea')
@section('description', '주식회사 앤디앤(N.D.N Korea)의 비전과 연혁, 협력 네트워크를 소개합니다.')

@section('content')

    <section class="nd-pagehero nd-pagehero--photo">
        <img class="nd-pagehero__bg" src="{{ asset('site/assets/img/landscape.jpg') }}" alt="" aria-hidden="true">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>회사소개</p>
            <h1 class="nd-h1">회사소개</h1>
            <p class="nd-lead">제도와 현장 사이에서, 양쪽 말을 모두 알아듣는 회사가 필요했습니다.</p>
        </div>
    </section>

    {{-- ==================== 대표 인사말 ==================== --}}
    <section class="nd-section">
        <div class="nd-wrap">
            <div class="nd-split">
                <div class="nd-split__a nd-rise">
                    <div class="nd-plate nd-plate--photo">
                        <img class="nd-plate__img" src="{{ asset('site/assets/img/handshake.jpg') }}" alt="협력 기관과 악수하는 장면">
                        <span class="nd-plate__k">2024</span>
                        <p class="nd-plate__t">주식회사 앤디앤 설립</p>
                        <p class="nd-plate__d">외국인 계절근로자(E-8) 관리·교육 사업으로 시작했습니다.</p>
                    </div>
                </div>
                <div class="nd-rise">
                    <span class="nd-eyebrow">Message</span>
                    <h2 class="nd-h2">함께 성장하는<br><span class="nd-mark">인력교류 플랫폼</span></h2>
                    <p class="nd-lead" style="margin-top:18px">
                        안녕하십니까. 급변하는 글로벌 환경 속에서 대한민국 농업과 어업 현장은
                        심각한 인력 부족 문제에 직면해 있습니다. 이에 주식회사 앤디앤(NDN Co., Ltd.)은
                        외국인 근로자와 대한민국 농·어업 현장이 함께 성장할 수 있는
                        지속가능한 인력교류 플랫폼을 구축하고자 설립되었습니다.
                    </p>
                    <p class="nd-lead" style="margin-top:14px">
                        당사는 외국인 계절근로자(E-8) 사업을 중심으로 해외 인재 발굴, 위생·안전 및 문화 교육,
                        입국 후 생활관리, 근로환경 지원, 귀국 후 사후관리까지 체계적인 운영 시스템을
                        구축하고 있습니다. 근로자와 고용주 모두가 만족하는 상생 모델로 대한민국 농촌의
                        경쟁력 강화에 기여하고자 노력하고 있습니다.
                    </p>
                    <p class="nd-lead" style="margin-top:14px">
                        또한 해외 정부기관·지방자치단체·교육기관 및 협력기관과의 긴밀한 네트워크를 통해
                        투명하고 신뢰할 수 있는 국제 인력교류 체계를 만들어가고 있습니다.
                    </p>
                    <p style="margin-top:24px;font-weight:800;color:var(--nd-text)">주식회사 앤디앤 대표이사 전병언</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 비전 ==================== --}}
    <section class="nd-section nd-band">
        <div class="nd-wrap">
            <div class="nd-sechead nd-sechead--center nd-rise">
                <span class="nd-eyebrow">Vision</span>
                <h2 class="nd-h2">세 가지를 지킵니다</h2>
            </div>
            <div class="nd-grid nd-grid--3" style="margin-top:44px">
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">01</span>
                    <h3 class="nd-h3">Global</h3>
                    <p>송출국 기관과 직접 연결된 채널을 유지합니다. 중간 단계가 줄면 근로자가 부담하는 비용도 함께 줄어듭니다.</p>
                </article>
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">02</span>
                    <h3 class="nd-h3">Professional</h3>
                    <p>제도 변경과 서류 요건을 먼저 읽고 반영합니다. 담당자가 공문을 찾아 헤매지 않게 합니다.</p>
                </article>
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">03</span>
                    <h3 class="nd-h3">Trustworthy</h3>
                    <p>개인정보는 필요한 범위에서만 다룹니다. 누가 언제 무엇을 열람했는지 기록으로 남깁니다.</p>
                </article>
            </div>
        </div>
    </section>

    {{-- ==================== 연혁 ==================== --}}
    <section class="nd-section">
        <div class="nd-wrap">
            <div class="nd-split">
                <div class="nd-rise">
                    <span class="nd-eyebrow">History</span>
                    <h2 class="nd-h2">연혁</h2>

                    <div class="nd-timeline">
                        <div class="nd-tl">
                            <span class="nd-tl__y">2024</span>
                            <h3>주식회사 앤디앤(NDN Co., Ltd.) 설립</h3>
                            <p>외국인 계절근로자 관리·교육 사업 개시, 농업분야 외국인 인력관리 시스템 구축</p>
                        </div>
                        <div class="nd-tl">
                            <span class="nd-tl__y">2025</span>
                            <h3>송출국 협력체계 구축 및 MOU 체결</h3>
                            <p>방글라데시 현지 NDN 교육서비스센터 설립, 방글라데시 노동국 산하 국영 송출기업 보이셀(BOESL)·중국 협력기관과 MOU, 당진시 외국인근로자 교육·행정서비스 지원 구축</p>
                        </div>
                        <div class="nd-tl">
                            <span class="nd-tl__y">2025.09</span>
                            <h3>계절근로자 공급 MOU</h3>
                            <p>당진시 주체·NDN 주관, 방글라데시 노동국 계절근로자 공급 협약 체결, 안전교육 프로그램 운영</p>
                        </div>
                        <div class="nd-tl">
                            <span class="nd-tl__y">2026</span>
                            <h3>당진시 농가 계절근로자 공급 서비스 개시</h3>
                            <p>현장 모니터링 시스템 운영, 당진시 교육프로그램 참여업체 선정, 지자체·농가 협력사업 확대(당진시 헬프 지원센터 설립)</p>
                        </div>
                    </div>
                </div>
                <div class="nd-split__a nd-rise">
                    <div class="nd-plate nd-plate--photo">
                        <img class="nd-plate__img" src="{{ asset('site/assets/img/mou_bangladesh.jpg') }}" alt="방글라데시 노동국에서 계절근로자 공급 협약을 서명하는 장면">
                        <span class="nd-plate__k">BOESL</span>
                        <p class="nd-plate__t">방글라데시 노동국 산하 국영 송출기업</p>
                        <p class="nd-plate__d">현지 교육서비스센터와 직접 연결된 모집 채널을 운영합니다.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 사업자 정보 ==================== --}}
    <section class="nd-section nd-section--muted">
        <div class="nd-wrap">
            <div class="nd-sechead nd-rise">
                <span class="nd-eyebrow">Company</span>
                <h2 class="nd-h2">사업자 정보</h2>
            </div>
            <div class="nd-tablewrap nd-rise" style="margin-top:30px">
                <table class="nd-table">
                    <caption class="nd-sr">N.D.N Korea 사업자 정보</caption>
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

    <section class="nd-cta nd-band">
        <div class="nd-wrap">
            <h2 class="nd-h2">협업을 논의하고 싶으신가요</h2>
            <p class="nd-lead">지자체, 농협, 송출기관 모두 환영합니다.</p>
            <div class="nd-btnrow">
                <a class="nd-btn nd-btn--accent" href="{{ route('site.contact') }}">문의하기</a>
                <a class="nd-btn nd-btn--onink" href="{{ route('site.partners') }}">협력기관 보기</a>
            </div>
        </div>
    </section>
@endsection
