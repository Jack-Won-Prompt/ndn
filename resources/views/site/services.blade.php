@extends('site.layout')

@section('title', '서비스 — N.D.N Korea')
@section('description', '모집·선별, 사전 교육, 현장 관리, 인재풀 네트워크까지 N.D.N Korea의 서비스를 소개합니다.')

@section('content')


    <section class="page-head">
        <img class="page-head__bg" src="{{ asset('site/assets/img/hero_interior.jpg') }}" alt="">
        <div class="wrap page-head__inner">
            <p class="crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>서비스</p>
            <h1>서비스</h1>
            <p>모집에서 사후관리까지, 단계마다 무엇을 하는지 구체적으로 적었습니다.</p>
        </div>
    </section>

    <!-- ============ 모집 & 선별 ============ -->
    <section class="section" id="recruitment">
        <div class="wrap">
            <div class="split">
                <div>
                    <span class="eyebrow">01 — Recruitment</span>
                    <div class="rule"></div>
                    <h2>모집 &amp; 선별</h2>
                    <p>
                        농가가 원하는 조건은 단순히 인원 수가 아닙니다.
                        품목과 작업 강도, 근무 기간, 성별과 연령대, 형제나 부부가 함께 오는지까지 다릅니다.
                        수요 신청서에서 이 조건을 먼저 받아 송출국에 그대로 전달합니다.
                    </p>
                    <ul class="checks">
                        <li>농가별 수요 신청 → 시청 취합 → Demand Letter 발행</li>
                        <li>송출국 현지 모집 공고 및 서류 접수</li>
                        <li>현지 면접 및 평가 기록 — 합격 / 보류 / 불합격</li>
                        <li>보류자는 대기열로 관리해 다음 회차에 재활용</li>
                    </ul>
                </div>
                <div class="split__media">
                    <div class="photo photo--wide">
                        <img src="{{ asset('site/assets/img/interview.jpg') }}" alt="현지 면접 장면">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 교육 ============ -->
    <section class="section section--gray" id="education">
        <div class="wrap">
            <div class="split split--reverse">
                <div>
                    <span class="eyebrow">02 — Education</span>
                    <div class="rule"></div>
                    <h2>사전 교육</h2>
                    <p>
                        입국 후에 배우는 것과 입국 전에 알고 오는 것은 정착 속도가 다릅니다.
                        현지 교육은 출국 준비 기간에 맞춰 진행합니다.
                    </p>
                    <ul class="checks">
                        <li><strong>한국어</strong> — 작업 지시와 안전 표지를 알아듣는 수준까지</li>
                        <li><strong>생활 규칙</strong> — 숙소 사용, 단체 생활, 급여 수령 방식</li>
                        <li><strong>산업 안전</strong> — 농기계, 농약, 온열질환 대응</li>
                    </ul>
                    <p style="margin-top:24px;font-size:14px;color:var(--gray-500)">
                        교육 자료는 방글라어·라오어·싱할라어·베트남어로 제공됩니다.
                    </p>
                </div>
                <div class="split__media">
                    <div class="photo photo--wide">
                        <img src="{{ asset('site/assets/img/safety_training.jpg') }}" alt="안전 교육 수업 장면">
                    </div>
                </div>
            </div>

            <div class="grid grid--3" style="margin-top:56px">
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/education.png') }}" alt=""></div>
                    <h3>한국어 교육</h3>
                    <p>인사와 숫자, 작업 용어, 몸이 아플 때 쓸 표현부터 가르칩니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/living.png') }}" alt=""></div>
                    <h3>생활 교육</h3>
                    <p>숙소 규칙, 쓰레기 분리, 이웃 관계. 사소해 보이지만 갈등의 대부분이 여기서 시작됩니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/management.png') }}" alt=""></div>
                    <h3>안전 교육</h3>
                    <p>농기계 조작, 농약 취급, 폭염 대응. 사고는 대개 첫 달에 일어납니다.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============ 현장 관리 ============ -->
    <section class="section" id="management">
        <div class="wrap">
            <div class="split">
                <div>
                    <span class="eyebrow">03 — Management</span>
                    <div class="rule"></div>
                    <h2>현장 관리</h2>
                    <p>
                        배치가 끝이 아니라 시작입니다.
                        점검자가 농가를 직접 방문해 체크인하고, 매달 근로자와 인터뷰합니다.
                    </p>
                    <ul class="checks">
                        <li>월별 인터뷰 6개 항목 — 급여 수령, 차별, 생활 규칙, 단체 생활, 건강, 이탈 징후</li>
                        <li>점검자 방문 체크인 기록</li>
                        <li>민원 접수 — 문의 / 연장 / 조기 귀국</li>
                        <li>긴급 상황 시 SOS 알림</li>
                    </ul>
                </div>
                <div class="split__media">
                    <div class="photo photo--wide">
                        <img src="{{ asset('site/assets/img/farm_guidance.jpg') }}" alt="현장에서 근로자에게 작업을 지도하는 장면">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 행정 · 정착 ============ -->
    <section class="section section--dark">
        <div class="wrap">
            <div class="sec-head sec-head--center">
                <span class="eyebrow">04 — Administration</span>
                <div class="rule"></div>
                <h2>행정과 정착 지원</h2>
                <p>입국 첫 주에 몰리는 일들입니다. 미리 예약해 두면 근로자가 이틀을 절약합니다.</p>
            </div>
            <div class="grid grid--4">
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/visa.png') }}" alt=""></div>
                    <h3>비자 · 입국</h3>
                    <p>서류 준비와 발급 일정 관리, 항공 예약, 공항 픽업 배차까지.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/admin.png') }}" alt=""></div>
                    <h3>체류 행정</h3>
                    <p>외국인 등록, 체류 기간 관리. 기한이 다가오면 담당자에게 먼저 알립니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/living.png') }}" alt=""></div>
                    <h3>통장 · 보험</h3>
                    <p>급여 계좌 개설과 보험 가입. 제휴 대리점과 연계해 처리합니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/network.png') }}" alt=""></div>
                    <h3>통신 · 유심</h3>
                    <p>연락이 닿지 않으면 관리도 불가능합니다. 입국 당일 개통을 목표로 합니다.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============ 서비스 범위 표 ============ -->
    <section class="section section--gray">
        <div class="wrap">
            <div class="sec-head">
                <span class="eyebrow">Scope</span>
                <div class="rule"></div>
                <h2>누가 무엇을 하는지</h2>
                <p>역할이 겹치거나 비는 구간이 없도록 미리 나눠 둡니다.</p>
            </div>
            <div class="table-scroll">
                <table class="tbl">
                    <caption class="sr-only">단계별 주체와 산출물</caption>
                    <thead>
                        <tr><th>단계</th><th>주관</th><th>N.D.N 역할</th><th>산출물</th></tr>
                    </thead>
                    <tbody>
                        <tr><td>수요 신청</td><td>농가 · 시청</td><td>신청 양식 제공, 취합 지원</td><td>수요 집계표</td></tr>
                        <tr><td>모집 요청</td><td>시청</td><td>송출국 전달 및 조율</td><td>Demand Letter</td></tr>
                        <tr><td>현지 면접</td><td>송출기관</td><td>평가 기준 제공, 결과 기록</td><td>후보자 평가서</td></tr>
                        <tr><td>온보딩</td><td>근로자</td><td>모국어 입력 화면 제공</td><td>본인 기입 서류</td></tr>
                        <tr><td>매칭 · 배치</td><td>시청 · 농가</td><td>조건 대조 및 그룹 매칭</td><td>배치 확정서</td></tr>
                        <tr><td>입국 · 이송</td><td>N.D.N</td><td>항공 · 픽업 · 배차 운영</td><td>이송 계획표</td></tr>
                        <tr><td>정착 서비스</td><td>제휴 대리점</td><td>배정 및 진행 상태 관리</td><td>처리 완료 내역</td></tr>
                        <tr><td>사후 관리</td><td>N.D.N</td><td>월별 인터뷰 · 현장 점검</td><td>월간 관리 보고서</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="wrap">
            <h2>어느 단계부터 필요하신가요</h2>
            <p>전 과정이 아니라 일부 구간만 맡는 것도 가능합니다.</p>
            <div class="btn-row">
                <a class="btn btn--light" href="{{ route('site.contact') }}">상담 요청</a>
            </div>
        </div>
    </section>
@endsection
