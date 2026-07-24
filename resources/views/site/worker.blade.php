@extends('site.layout')

@section('title', '근로자 지원 — N.D.N Korea')
@section('description', '입국 전 준비, 한국 생활 안내, 농업 현장 안내와 자주 묻는 질문.')

@section('content')


    <section class="page-head">
        <img class="page-head__bg" src="{{ asset('site/assets/img/harvest.jpg') }}" alt="">
        <div class="wrap page-head__inner">
            <p class="crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>근로자 지원</p>
            <h1>근로자 지원</h1>
            <p>한국에 오기 전에 알아 두면 좋은 것들을 정리했습니다.</p>
        </div>
    </section>

    <!-- 다국어 안내 배너 -->
    <section class="section section--tight section--dark">
        <div class="wrap">
            <div class="countries">
                <span class="country"><span class="country__code">KO</span> 한국어</span>
                <span class="country"><span class="country__code">BN</span> বাংলা</span>
                <span class="country"><span class="country__code">LO</span> ລາວ</span>
                <span class="country"><span class="country__code">SI</span> සිංහල</span>
                <span class="country"><span class="country__code">VI</span> Tiếng Việt</span>
            </div>
        </div>
    </section>

    <!-- ============ 입국 전 준비 ============ -->
    <section class="section" id="before">
        <div class="wrap">
            <div class="split">
                <div>
                    <span class="eyebrow">Before Departure</span>
                    <div class="rule"></div>
                    <h2>입국 전 준비</h2>
                    <p>출국 전에 끝내 두면 한국에서 보내는 첫 주가 훨씬 수월해집니다.</p>
                    <ul class="checks">
                        <li>여권 유효기간 확인 — 체류 예정 기간보다 길어야 합니다</li>
                        <li>사전 교육 이수 — 한국어, 생활 규칙, 산업 안전</li>
                        <li>본인 정보 입력 — 앱에서 모국어로 직접 작성합니다</li>
                        <li>건강검진 결과 제출</li>
                        <li>가족 연락처 등록 — 긴급 상황 시 연락할 곳</li>
                    </ul>
                </div>
                <div class="split__media">
                    <div class="photo photo--wide">
                        <img src="{{ asset('site/assets/img/culture_class.jpg') }}" alt="한국어 수업 장면">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 한국 생활 ============ -->
    <section class="section section--gray" id="living">
        <div class="wrap">
            <div class="sec-head">
                <span class="eyebrow">Living in Korea</span>
                <div class="rule"></div>
                <h2>한국 생활 안내</h2>
                <p>숙소와 급여, 그리고 아플 때 어떻게 하는지.</p>
            </div>

            <div class="grid grid--3">
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/living.png') }}" alt=""></div>
                    <h3>숙소</h3>
                    <p>농가가 제공하는 숙소에서 생활합니다. 입주 전 상태를 함께 확인하고 기록으로 남깁니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/admin.png') }}" alt=""></div>
                    <h3>급여</h3>
                    <p>본인 명의 계좌로 받습니다. 현금이나 타인 계좌로 받는 것은 정상적인 방식이 아닙니다.</p>
                </article>
                <article class="card">
                    <div class="card__icon"><img src="{{ asset('site/assets/icons/aftercare.png') }}" alt=""></div>
                    <h3>건강 · 보험</h3>
                    <p>보험에 가입되어 있습니다. 다치거나 아프면 참지 말고 바로 담당자에게 알리십시오.</p>
                </article>
            </div>

            <div class="split" style="margin-top:64px">
                <div class="split__media">
                    <div class="photo photo--wide">
                        <img src="{{ asset('site/assets/img/strawberry.jpg') }}" alt="채소 선별 포장 작업">
                    </div>
                </div>
                <div>
                    <h2>농업 현장 안내</h2>
                    <p>
                        계절근로는 작물과 시기에 따라 하는 일이 달라집니다.
                        배치받은 농가의 품목에 맞춰 필요한 작업을 배우게 됩니다.
                    </p>
                    <ul class="checks">
                        <li>파종 · 정식 — 모종을 옮겨 심는 작업</li>
                        <li>관리 — 물주기, 순치기, 병해충 확인</li>
                        <li>수확 — 작물별로 시기와 방법이 다릅니다</li>
                        <li>선별 · 포장 — 크기와 상태에 따라 나누는 작업</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ 도움 요청 ============ -->
    <section class="section section--dark">
        <div class="wrap">
            <div class="sec-head sec-head--center">
                <span class="eyebrow">Support</span>
                <div class="rule"></div>
                <h2>도움이 필요할 때</h2>
                <p>혼자 참지 마십시오. 모국어로 이야기할 수 있습니다.</p>
            </div>
            <div class="grid grid--3">
                <article class="card">
                    <h3>월별 인터뷰</h3>
                    <p>매달 담당자가 연락합니다. 급여, 차별, 건강, 생활 문제를 이때 이야기하십시오.</p>
                </article>
                <article class="card">
                    <h3>민원 접수</h3>
                    <p>문의, 계약 연장, 조기 귀국 요청을 앱에서 접수할 수 있습니다.</p>
                </article>
                <article class="card">
                    <h3>긴급 SOS</h3>
                    <p>사고나 위급한 상황에서는 앱의 SOS 버튼을 누르십시오. 즉시 담당자에게 전달됩니다.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- ============ FAQ ============ -->
    <section class="section" id="faq">
        <div class="wrap">
            <div class="sec-head sec-head--center">
                <span class="eyebrow">FAQ</span>
                <div class="rule"></div>
                <h2>자주 묻는 질문</h2>
            </div>

            <div class="faq">
                <details>
                    <summary>계절근로자로 얼마 동안 일할 수 있나요?</summary>
                    <div class="faq__body"><p>체류 기간은 배정받은 프로그램과 계약 조건에 따라 정해집니다. 정확한 기간은 계약서에 적힌 내용을 확인하십시오.</p></div>
                </details>
                <details>
                    <summary>급여는 어떻게 받나요?</summary>
                    <div class="faq__body"><p>본인 명의 계좌로 입금됩니다. 입국 후 통장 개설을 도와드립니다. 현금으로 받거나 다른 사람 계좌로 받는 것은 정상적인 방식이 아니므로 담당자에게 알려 주십시오.</p></div>
                </details>
                <details>
                    <summary>일하는 농가를 바꿀 수 있나요?</summary>
                    <div class="faq__body"><p>정해진 절차와 사유가 있는 경우에 한해 가능합니다. 먼저 담당자와 상담하십시오. 무단으로 이탈하면 체류 자격에 문제가 생깁니다.</p></div>
                </details>
                <details>
                    <summary>아프거나 다치면 어떻게 하나요?</summary>
                    <div class="faq__body"><p>즉시 농가와 담당자에게 알리십시오. 보험이 적용됩니다. 위급한 경우 앱의 SOS 버튼을 누르십시오.</p></div>
                </details>
                <details>
                    <summary>가족이나 형제와 함께 배치될 수 있나요?</summary>
                    <div class="faq__body"><p>함께 신청한 경우 같은 농가나 인근 농가로 묶어 배치하려 합니다. 다만 농가의 수요 조건에 따라 달라질 수 있습니다.</p></div>
                </details>
                <details>
                    <summary>한국어를 못 해도 괜찮나요?</summary>
                    <div class="faq__body"><p>입국 전 교육에서 기본 표현을 배웁니다. 안내와 알림은 모국어로 제공되며, 상담도 모국어로 가능합니다.</p></div>
                </details>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="wrap">
            <h2>더 궁금한 점이 있으신가요</h2>
            <p>담당자에게 직접 물어보실 수 있습니다.</p>
            <div class="btn-row">
                <a class="btn btn--light" href="{{ route('site.contact') }}">문의하기</a>
            </div>
        </div>
    </section>
@endsection
