@extends('site.layout')

@section('title', 'N.D.N Korea — 외국인 계절근로자 통합관리')
@section('description', '주식회사 앤디앤(N.D.N Korea)은 외국인 계절근로자(E-8)의 모집·교육·입국·배치·사후관리 전 과정을 하나의 체계로 운영합니다.')

@section('content')


    <!-- ============ Hero ============ -->
    <section class="hero">
        <img class="hero__bg" src="{{ asset('site/assets/img/hero_greenhouse.jpg') }}" alt="">
        <div class="wrap hero__inner">
            <div class="hero__content">
                <span class="hero__tag">외국인 계절근로자 · E-8</span>
                <h1>모집부터 귀국까지<br>하나의 데이터로 관리합니다</h1>
                <p class="hero__lead">
                    농가 수요 신청, 송출국 모집과 현지 면접, 입국과 배치, 정착 서비스,
                    월별 사후관리까지. 흩어져 있던 계절근로자 행정을 N.D.N Korea가 하나의 흐름으로 잇습니다.
                </p>
                <div class="btn-row">
                    <a class="btn btn--light" href="{{ route('site.services') }}">서비스 살펴보기</a>
                    <a class="btn btn--ghost" href="{{ route('site.contact') }}">도입 문의</a>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 통계 ============ -->
    <section class="section section--tight section--dark">
        <div class="wrap">
            @php
                $hasStats = ($S['stats.countries'] ?? '') !== '' || ($S['stats.workers'] ?? '') !== ''
                    || ($S['stats.cities'] ?? '') !== '' || ($S['stats.return_rate'] ?? '') !== '';
            @endphp
            <div class="stats">
                <div class="stat">
                    <span class="stat__num">{{ $S['stats.countries'] ?? '○○' }}<small>개국</small></span>
                    <span class="stat__label">송출 협력국</span>
                </div>
                <div class="stat">
                    <span class="stat__num">{{ $S['stats.workers'] ?? '○○○' }}<small>명</small></span>
                    <span class="stat__label">누적 입국 근로자</span>
                </div>
                <div class="stat">
                    <span class="stat__num">{{ $S['stats.cities'] ?? '○○' }}<small>개</small></span>
                    <span class="stat__label">협약 지자체</span>
                </div>
                <div class="stat">
                    <span class="stat__num">{{ $S['stats.return_rate'] ?? '○○' }}<small>%</small></span>
                    <span class="stat__label">계약 만료 귀국률</span>
                </div>
            </div>
            @unless ($hasStats)
                <p style="margin:18px 0 0;font-size:13px;color:rgba(255,255,255,.45)">
                    ⚠ 수치는 자리표시자입니다. 운영 콘솔 › 사이트 설정에서 실제 실적을 입력하세요.
                </p>
            @endunless
        </div>
    </section>

    <!-- ============ 회사 소개 ============ -->
    <section class="section">
        <div class="wrap">
            <div class="split">
                <div class="split__media">
                    <div class="photo photo--wide">
                        <img src="{{ asset('site/assets/img/business_meeting.jpg') }}" alt="협약식 장면">
                    </div>
                </div>
                <div>
                    <span class="eyebrow">About N.D.N</span>
                    <div class="rule"></div>
                    <h2>제도를 아는 사람이<br>현장을 함께 봅니다</h2>
                    <p>
                        계절근로자 제도는 지자체·농가·송출기관·근로자가 각각 다른 서류와 일정으로 움직입니다.
                        어느 한 곳이 늦으면 입국 일정 전체가 밀립니다.
                    </p>
                    <p>
                        N.D.N Korea는 이 네 주체를 같은 화면 위에 올려 두고,
                        지금 무엇이 막혀 있는지 서로가 볼 수 있게 만듭니다.
                    </p>
                    <ul class="checks">
                        <li>농가 수요 신청부터 Demand Letter 발행까지 한 번에</li>
                        <li>송출국 현지 면접 결과와 후보자 이력을 그대로 승계</li>
                        <li>입국 이후 월별 인터뷰로 이탈 징후를 조기에 확인</li>
                    </ul>
                    <div class="btn-row" style="margin-top:32px">
                        <a class="btn btn--dark" href="{{ route('site.about') }}">회사소개 자세히</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 핵심 서비스 6종 ============ -->
    <section class="section section--gray">
        <div class="wrap">
            <div class="sec-head sec-head--center">
                <span class="eyebrow">Services</span>
                <div class="rule"></div>
                <h2>여섯 갈래의 일을 한 팀이 맡습니다</h2>
                <p>모집과 교육, 행정과 생활 지원은 원래 서로 다른 회사가 나눠 하던 일입니다. 나뉘어 있으면 책임도 나뉩니다.</p>
            </div>

            <div class="grid grid--3">
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/recruitment.png') }}" alt=""></div>
                    <h3>모집 &amp; 선별</h3>
                    <p>송출국 현지에서 후보자를 모으고 면접으로 거릅니다. 합격·보류·불합격 사유를 기록으로 남겨 다음 회차에 그대로 씁니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/education.png') }}" alt=""></div>
                    <h3>사전 교육</h3>
                    <p>한국어, 생활 규칙, 농작업 안전. 입국 전에 알고 오는 것과 와서 배우는 것은 정착 속도가 다릅니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/management.png') }}" alt=""></div>
                    <h3>현장 관리</h3>
                    <p>배치 이후가 진짜 시작입니다. 점검자가 농가를 방문해 체크인하고, 월별 인터뷰로 상태를 확인합니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/network.png') }}" alt=""></div>
                    <h3>인재풀 네트워크</h3>
                    <p>송출국 정부·기관과 직접 연결된 채널을 유지합니다. 중간 단계가 줄면 근로자 부담 비용도 줄어듭니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/admin.png') }}" alt=""></div>
                    <h3>행정 지원</h3>
                    <p>비자 서류, 입국 신고, 체류 기간 관리. 담당자가 놓치기 쉬운 기한을 시스템이 먼저 알립니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/living.png') }}" alt=""></div>
                    <h3>생활 지원</h3>
                    <p>통장 개설, 보험 가입, 통신·유심. 입국 첫 주에 몰리는 일들을 대리점과 연계해 처리합니다.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============ 5단계 프로세스 ============ -->
    <section class="section section--dark">
        <div class="wrap">
            <div class="sec-head sec-head--center">
                <span class="eyebrow">Process</span>
                <div class="rule"></div>
                <h2>다섯 단계, 끊기지 않는 기록</h2>
                <p>각 단계에서 남긴 정보가 다음 단계로 그대로 넘어갑니다. 같은 내용을 다시 묻지 않습니다.</p>
            </div>

            <ol class="steps">
                <li class="step">
                    <div class="step__num"><img src="{{ asset('site/assets/icons/recruitment.png') }}" alt=""></div>
                    <span class="step__label">STEP 01</span>
                    <h3>해외 모집</h3>
                    <p>농가 수요를 모아 송출국에 전달하고 후보자를 모집합니다.</p>
                </li>
                <li class="step">
                    <div class="step__num"><img src="{{ asset('site/assets/icons/education.png') }}" alt=""></div>
                    <span class="step__label">STEP 02</span>
                    <h3>사전 교육</h3>
                    <p>현지에서 한국어와 안전 교육을 마치고 출국을 준비합니다.</p>
                </li>
                <li class="step">
                    <div class="step__num"><img src="{{ asset('site/assets/icons/visa.png') }}" alt=""></div>
                    <span class="step__label">STEP 03</span>
                    <h3>입국 지원</h3>
                    <p>비자 발급과 항공, 공항 픽업과 이송까지 배차로 관리합니다.</p>
                </li>
                <li class="step">
                    <div class="step__num"><img src="{{ asset('site/assets/icons/farm.png') }}" alt=""></div>
                    <span class="step__label">STEP 04</span>
                    <h3>현장 배치</h3>
                    <p>농가 조건과 근로자 이력을 맞춰 배치합니다. 가족은 함께 묶습니다.</p>
                </li>
                <li class="step">
                    <div class="step__num"><img src="{{ asset('site/assets/icons/aftercare.png') }}" alt=""></div>
                    <span class="step__label">STEP 05</span>
                    <h3>사후 관리</h3>
                    <p>월별 인터뷰와 현장 점검으로 계약 만료까지 함께합니다.</p>
                </li>
            </ol>
        </div>
    </section>

    <!-- ============ 송출국 ============ -->
    <section class="section">
        <div class="wrap">
            <div class="split split--reverse">
                <div class="split__media">
                    <div class="photo photo--wide">
                        <img src="{{ asset('site/assets/img/korean_class.jpg') }}" alt="송출국 출국 준비 장면">
                    </div>
                </div>
                <div>
                    <span class="eyebrow">Countries</span>
                    <div class="rule"></div>
                    <h2>네 개 언어로<br>같은 내용을 전합니다</h2>
                    <p>
                        근로자에게 가는 안내문·알림·서류는 모두 모국어로 나갑니다.
                        한국어만 적힌 종이를 손에 쥐여 주고 이해했으리라 가정하지 않습니다.
                    </p>

                    <div class="countries" style="margin-top:26px">
                        <span class="country"><span class="country__code">BD</span> 방글라데시 <span class="country__lang">বাংলা</span></span>
                        <span class="country"><span class="country__code">LA</span> 라오스 <span class="country__lang">ລາວ</span></span>
                        <span class="country"><span class="country__code">LK</span> 스리랑카 <span class="country__lang">සිංහල</span></span>
                        <span class="country"><span class="country__code">VN</span> 베트남 <span class="country__lang">Tiếng Việt</span></span>
                    </div>

                    <p style="margin-top:22px;font-size:13px;color:var(--gray-500)">
                        ⚠ 협력국 목록은 프로젝트 사양(지원 언어 5종)에서 유추한 것입니다.
                        실제 협약 체결국으로 확인 후 확정하십시오.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ CTA ============ -->
    <section class="cta">
        <div class="wrap">
            <h2>계절근로자 도입을 준비하고 계신가요</h2>
            <p>지자체 담당자와 농가 모두 문의할 수 있습니다. 필요한 서류와 일정부터 안내해 드립니다.</p>
            <div class="btn-row">
                <a class="btn btn--light" href="{{ route('site.contact') }}">문의하기</a>
                <a class="btn btn--ghost" href="{{ route('site.worker') }}">근로자 지원 안내</a>
            </div>
        </div>
    </section>
@endsection
