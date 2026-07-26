@extends('site.layout')

@section('title', '협력기관 — N.D.N Korea')
@section('description', '국내 지자체·농협·대학, 해외 송출기관과의 협력 네트워크.')

@section('content')


    <section class="page-head">
        <img class="page-head__bg" src="{{ asset('site/assets/img/partnership_meeting.jpg') }}" alt="">
        <div class="wrap page-head__inner">
            <p class="crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>협력기관</p>
            <h1>협력기관</h1>
            <p>혼자 할 수 있는 일이 아닙니다. 국내외 기관과 함께 움직입니다.</p>
        </div>
    </section>

    <!-- ============ 국내 ============ -->
    <section class="section">
        <div class="wrap">
            <div class="sec-head">
                <span class="eyebrow">Domestic</span>
                <div class="rule"></div>
                <h2>국내 협력기관</h2>
                <p>수요를 취합하고 배치를 확정하는 주체들입니다.</p>
            </div>

            <div class="grid grid--4">
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/admin.png') }}" alt=""></div>
                    <h3>지방자치단체</h3>
                    <p>충청남도 당진시, 경상남도 창녕군. 계절근로자 프로그램의 운영 주체로 농가 수요를 취합하고 배정 인원을 확정합니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/farm.png') }}" alt=""></div>
                    <h3>지역 농협 · 농가</h3>
                    <p>당진시 · 창녕군 지역 농협과 농가. 실제 근로가 이루어지는 현장으로 숙소와 작업 환경을 함께 점검합니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/education.png') }}" alt=""></div>
                    <h3>교육기관</h3>
                    <p>청주대학교, 보건과학대학교, 충청대학교, 신성대학교. 한국어·문화 교육 과정과 유학 서비스를 함께 설계합니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/network.png') }}" alt=""></div>
                    <h3>산업체 · 협회</h3>
                    <p>대한민국 축산협회, 봉제가공협회, 충청남도 내수면 어업협회 등 농·어·축산 분야 협력.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============ 해외 ============ -->
    <section class="section section--dark">
        <div class="wrap">
            <div class="sec-head">
                <span class="eyebrow">Overseas</span>
                <div class="rule"></div>
                <h2>해외 송출기관</h2>
                <p>현지 모집과 면접, 출국 준비를 담당하는 정부·공공 파트너입니다.</p>
            </div>

            <div class="grid grid--2">
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/network.png') }}" alt=""></div>
                    <h3>방글라데시 · 보이셀(BOESL)</h3>
                    <p>방글라데시 노동국 산하 국영 인력송출 기업 보이셀(BOESL)과 인력지원·교육·행정서비스 MOU를 체결했습니다. 방글라데시 현지에 NDN 교육서비스센터를 두어 모집·면접·사전교육을 직접 운영합니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/network.png') }}" alt=""></div>
                    <h3>중국 · 인력개발기관</h3>
                    <p>허베이성 인력개발자원공사(스좌장 인력개발교육원), 산동성 인력지원센터 등과 협력 체계를 구축하여 현지 모집과 교육을 진행합니다.</p>
                </article>
            </div>

            <p style="margin-top:28px;font-size:15px;color:var(--gray-500);max-width:760px">
                각 송출국의 현지 파트너가 모집 공고, 서류 접수, 면접, 출국 준비를 담당합니다.
                근로자는 모국어로 안내받으며, 출국 전 한국어 · 생활 · 안전 교육을 마칩니다.
            </p>
        </div>
    </section>

    <!-- ============ 제휴 대리점 ============ -->
    <section class="section section--gray">
        <div class="wrap">
            <div class="split">
                <div>
                    <span class="eyebrow">Partner Agency</span>
                    <div class="rule"></div>
                    <h2>정착 서비스 제휴사</h2>
                    <p>
                        통장, 보험, 통신, 유심. 입국 첫 주에 몰리는 일들을 지역 대리점과 나눠 처리합니다.
                    </p>
                    <ul class="checks">
                        <li>대리점은 자신에게 배정된 건만 조회할 수 있습니다</li>
                        <li>근로자 동의가 없는 정보는 대리점 화면에 나타나지 않습니다</li>
                        <li>내려받는 문서에는 대리점명 워터마크가 들어갑니다</li>
                    </ul>
                    <p style="margin-top:22px;font-size:14px;color:var(--gray-500)">
                        제3자 제공은 동의 범위 안에서만 이루어지며, 동의 이력은 목적별로 따로 보관됩니다.
                    </p>
                </div>
                <div class="split__media">
                    <div class="photo photo--wide">
                        <img src="{{ asset('site/assets/img/partnership_mou.jpg') }}" alt="해외 협력기관 업무협약 장면">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="wrap">
            <h2>협력 제안을 기다립니다</h2>
            <p>지자체, 농협, 송출기관, 정착 서비스 대리점 모두 문의하실 수 있습니다.</p>
            <div class="btn-row">
                <a class="btn btn--light" href="{{ route('site.contact') }}">제휴 문의</a>
            </div>
        </div>
    </section>
@endsection
