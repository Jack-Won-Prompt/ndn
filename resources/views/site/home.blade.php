@extends('site.layout')

@section('title', 'N.D.N Korea — 외국인 계절근로자 통합관리')
@section('description', '주식회사 앤디앤(N.D.N Korea)은 외국인 계절근로자(E-8)의 모집·교육·입국·배치·사후관리 전 과정을 하나의 체계로 운영합니다.')

@section('content')

    {{-- ==================== 히어로 ==================== --}}
    @php
        // 히어로 배경 사진 — 콘솔 [사이트 설정]에서 파일명을 넣었을 때만 깔린다.
        // 비워 두면 사진 없이 그라디언트만 쓴다. 라이선스가 확보된 파일만 넣을 것
        // (public/site/README.md 의 사진 감사 결과 참조).
        $heroImage = $S['site.hero_image'] ?? null;
        $heroSrc = filled($heroImage) ? asset('site/assets/img/'.$heroImage) : null;
    @endphp
    <section class="nd-hero @if ($heroSrc) nd-hero--photo @endif">
        @if ($heroSrc)
            <img class="nd-hero__bg" src="{{ $heroSrc }}" alt="" aria-hidden="true">
        @endif
        <div class="nd-wrap">
            <div class="nd-hero__in">
                <span class="nd-tag">외국인 계절근로자 · E-8</span>
                <h1 class="nd-display">
                    모집부터 귀국까지<br>
                    <span class="nd-mark">하나의 데이터</span>로 관리합니다
                </h1>
                <p class="nd-lead">
                    농가 수요 신청, 송출국 모집과 현지 면접, 입국과 배치, 정착 서비스,
                    월별 사후관리까지. 흩어져 있던 계절근로자 행정을 N.D.N Korea가 하나의 흐름으로 잇습니다.
                </p>
                <div class="nd-btnrow">
                    <a class="nd-btn nd-btn--accent" href="{{ route('site.services') }}">서비스 살펴보기</a>
                    <a class="nd-btn nd-btn--onink" href="{{ route('site.contact') }}">도입 문의</a>
                </div>
            </div>

            {{-- 지표는 관리자가 콘솔 [사이트 설정]에서 채운다. 비어 있으면 자리표시자가 보인다. --}}
            <dl class="nd-herometa">
                <div>
                    <dt>송출 협력국</dt>
                    <dd>{{ $S['stats.countries'] ?? '○○' }}<small>개국</small></dd>
                </div>
                <div>
                    <dt>누적 입국 근로자</dt>
                    <dd>{{ $S['stats.workers'] ?? '○○○' }}<small>명</small></dd>
                </div>
                <div>
                    <dt>협약 지자체</dt>
                    <dd>{{ $S['stats.cities'] ?? '○○' }}<small>개</small></dd>
                </div>
                <div>
                    <dt>계약 만료 귀국률</dt>
                    <dd>{{ $S['stats.return_rate'] ?? '○○' }}<small>%</small></dd>
                </div>
            </dl>
        </div>
    </section>

    {{-- ==================== 회사 소개 ==================== --}}
    <section class="nd-section">
        <div class="nd-wrap">
            <div class="nd-split">
                <div class="nd-split__a nd-rise">
                    <div class="nd-plate nd-plate--photo">
                        <img class="nd-plate__img" src="{{ asset('site/assets/img/business_meeting.jpg') }}" alt="지자체·농가 관계자 협의 장면">
                        <span class="nd-plate__k">4 : 1</span>
                        <p class="nd-plate__t">네 주체, 하나의 화면</p>
                        <p class="nd-plate__d">지자체 · 농가 · 송출기관 · 근로자가 같은 진행 상황을 봅니다.</p>
                    </div>
                </div>
                <div class="nd-rise">
                    <span class="nd-eyebrow">About N.D.N</span>
                    <h2 class="nd-h2">제도를 아는 사람이<br>현장을 함께 봅니다</h2>
                    <p class="nd-lead" style="margin-top:18px">
                        계절근로자 제도는 지자체·농가·송출기관·근로자가 각각 다른 서류와 일정으로 움직입니다.
                        어느 한 곳이 늦으면 입국 일정 전체가 밀립니다.
                    </p>
                    <p class="nd-lead" style="margin-top:14px">
                        N.D.N Korea는 이 네 주체를 같은 화면 위에 올려 두고,
                        지금 무엇이 막혀 있는지 서로가 볼 수 있게 만듭니다.
                    </p>
                    <ul class="nd-checks">
                        <li>농가 수요 신청부터 Demand Letter 발행까지 한 번에</li>
                        <li>송출국 현지 면접 결과와 후보자 이력을 그대로 승계</li>
                        <li>입국 이후 월별 인터뷰로 이탈 징후를 조기에 확인</li>
                    </ul>
                    <div class="nd-btnrow" style="margin-top:32px">
                        <a class="nd-btn nd-btn--ink" href="{{ route('site.about') }}">회사소개 자세히</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 핵심 서비스 6종 ==================== --}}
    <section class="nd-section nd-section--muted">
        <div class="nd-wrap">
            <div class="nd-sechead nd-sechead--center nd-rise">
                <span class="nd-eyebrow">Services</span>
                <h2 class="nd-h2">여섯 갈래의 일을 <span class="nd-mark">한 팀</span>이 맡습니다</h2>
                <p class="nd-lead">모집과 교육, 행정과 생활 지원은 원래 서로 다른 회사가 나눠 하던 일입니다. 나뉘어 있으면 책임도 나뉩니다.</p>
            </div>

            <div class="nd-grid nd-grid--3" style="margin-top:48px">
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">01</span>
                    <h3 class="nd-h3">모집 &amp; 선별</h3>
                    <p>송출국 현지에서 후보자를 모으고 면접으로 거릅니다. 합격·보류·불합격 사유를 기록으로 남겨 다음 회차에 그대로 씁니다.</p>
                </article>
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">02</span>
                    <h3 class="nd-h3">사전 교육</h3>
                    <p>한국어, 생활 규칙, 농작업 안전. 입국 전에 알고 오는 것과 와서 배우는 것은 정착 속도가 다릅니다.</p>
                </article>
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">03</span>
                    <h3 class="nd-h3">현장 관리</h3>
                    <p>배치 이후가 진짜 시작입니다. 점검자가 농가를 방문해 체크인하고, 월별 인터뷰로 상태를 확인합니다.</p>
                </article>
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">04</span>
                    <h3 class="nd-h3">인재풀 네트워크</h3>
                    <p>송출국 정부·기관과 직접 연결된 채널을 유지합니다. 중간 단계가 줄면 근로자 부담 비용도 줄어듭니다.</p>
                </article>
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">05</span>
                    <h3 class="nd-h3">행정 지원</h3>
                    <p>비자 서류, 입국 신고, 체류 기간 관리. 담당자가 놓치기 쉬운 기한을 시스템이 먼저 알립니다.</p>
                </article>
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">06</span>
                    <h3 class="nd-h3">생활 지원</h3>
                    <p>통장 개설, 보험 가입, 통신·유심. 입국 첫 주에 몰리는 일들을 대리점과 연계해 처리합니다.</p>
                </article>
            </div>
        </div>
    </section>

    {{-- ==================== 5단계 프로세스 ==================== --}}
    <section class="nd-section nd-band">
        <div class="nd-wrap">
            <div class="nd-sechead nd-sechead--center nd-rise">
                <span class="nd-eyebrow">Process</span>
                <h2 class="nd-h2">다섯 단계, <span class="nd-mark">끊기지 않는</span> 기록</h2>
                <p class="nd-lead">각 단계에서 남긴 정보가 다음 단계로 그대로 넘어갑니다. 같은 내용을 다시 묻지 않습니다.</p>
            </div>

            <ol class="nd-steps nd-rise" style="margin-top:48px">
                <li class="nd-step">
                    <span class="nd-step__n">STEP 01</span>
                    <h3>해외 모집</h3>
                    <p>농가 수요를 모아 송출국에 전달하고 후보자를 모집합니다.</p>
                </li>
                <li class="nd-step">
                    <span class="nd-step__n">STEP 02</span>
                    <h3>사전 교육</h3>
                    <p>현지에서 한국어와 안전 교육을 마치고 출국을 준비합니다.</p>
                </li>
                <li class="nd-step">
                    <span class="nd-step__n">STEP 03</span>
                    <h3>입국 지원</h3>
                    <p>비자 발급과 항공, 공항 픽업과 이송까지 배차로 관리합니다.</p>
                </li>
                <li class="nd-step">
                    <span class="nd-step__n">STEP 04</span>
                    <h3>현장 배치</h3>
                    <p>농가 조건과 근로자 이력을 맞춰 배치합니다. 가족은 함께 묶습니다.</p>
                </li>
                <li class="nd-step">
                    <span class="nd-step__n">STEP 05</span>
                    <h3>사후 관리</h3>
                    <p>월별 인터뷰와 현장 점검으로 계약 만료까지 함께합니다.</p>
                </li>
            </ol>
        </div>
    </section>

    {{-- ==================== 송출국·언어 ==================== --}}
    <section class="nd-section">
        <div class="nd-wrap">
            <div class="nd-split nd-split--reverse">
                <div class="nd-split__a nd-rise">
                    <div class="nd-plate nd-plate--photo">
                        <img class="nd-plate__img" src="{{ asset('site/assets/img/korean_class.jpg') }}" alt="송출국 현지 한국어 수업 장면">
                        <span class="nd-plate__k">5</span>
                        <p class="nd-plate__t">다섯 언어로 같은 내용을</p>
                        <p class="nd-plate__d" data-no-translate>한국어 · বাংলা · ລາວ · සිංහල · Tiếng Việt</p>
                    </div>
                </div>
                <div class="nd-rise">
                    <span class="nd-eyebrow">Countries</span>
                    <h2 class="nd-h2">모국어로 전하지 않으면<br><span class="nd-mark">전한 것이 아닙니다</span></h2>
                    <p class="nd-lead" style="margin-top:18px">
                        근로자에게 가는 안내문·알림·서류는 모두 모국어로 나갑니다.
                        한국어만 적힌 종이를 손에 쥐여 주고 이해했으리라 가정하지 않습니다.
                    </p>

                    <div class="nd-countries" style="margin-top:28px">
                        <span class="nd-country"><span class="nd-country__c">BD</span> 방글라데시 <span class="nd-country__l" data-no-translate>বাংলা</span></span>
                        <span class="nd-country"><span class="nd-country__c">LA</span> 라오스 <span class="nd-country__l" data-no-translate>ລາວ</span></span>
                        <span class="nd-country"><span class="nd-country__c">LK</span> 스리랑카 <span class="nd-country__l" data-no-translate>සිංහල</span></span>
                        <span class="nd-country"><span class="nd-country__c">VN</span> 베트남 <span class="nd-country__l" data-no-translate>Tiếng Việt</span></span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== CTA ==================== --}}
    <section class="nd-cta nd-band">
        <div class="nd-wrap">
            <h2 class="nd-h2">계절근로자 도입을 준비하고 계신가요</h2>
            <p class="nd-lead">지자체 담당자와 농가 모두 문의할 수 있습니다. 필요한 서류와 일정부터 안내해 드립니다.</p>
            <div class="nd-btnrow">
                <a class="nd-btn nd-btn--accent" href="{{ route('site.contact') }}">문의하기</a>
                <a class="nd-btn nd-btn--onink" href="{{ route('site.worker') }}">근로자 지원 안내</a>
            </div>
        </div>
    </section>
@endsection
