@extends('site.layout')

@section('title', '근로자 지원 — N.D.N Korea')
@section('description', '입국 전 준비, 한국 생활 안내, 농업 현장 안내와 자주 묻는 질문.')

@section('content')

    <section class="nd-pagehero nd-pagehero--photo">
        <img class="nd-pagehero__bg" src="{{ asset('site/assets/img/field_workers.jpg') }}" alt="" aria-hidden="true">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>근로자 지원</p>
            <h1 class="nd-h1">근로자 지원</h1>
            <p class="nd-lead">한국에 오기 전에 알아 두면 좋은 것들을 정리했습니다.</p>

            {{-- 근로자에게 안내가 나가는 언어. 목록을 여기 손으로 적으면 언어가 늘 때마다
                 빠뜨리므로 시스템 언어 목록에서 직접 뽑는다. en 은 근로자 대상이 아니라 제외.
                 언어 이름은 그 언어 문자 그대로 둔다(자동 번역 제외). --}}
            <div class="nd-countries" style="margin-top:28px" data-no-translate>
                @foreach (\App\Shared\Translation\SiteTranslator::NATIVE as $lc => $native)
                    @continue ($lc === 'en')
                    <span class="nd-country">
                        <span class="nd-country__c">{{ strtoupper($lc) }}</span> {{ $native }}
                    </span>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ==================== 입국 전 준비 ==================== --}}
    <section class="nd-section" id="before">
        <div class="nd-wrap">
            <div class="nd-split">
                <div class="nd-rise">
                    <span class="nd-eyebrow">Before Departure</span>
                    <h2 class="nd-h2">입국 전 준비</h2>
                    <p class="nd-lead" style="margin-top:18px">출국 전에 끝내 두면 한국에서 보내는 첫 주가 훨씬 수월해집니다.</p>
                    <ul class="nd-checks">
                        <li>근로위반 귀국동의서 — 무단결근·무단이탈 등 근로생활 위반 시 귀국에 동의(본인 서명)</li>
                        <li>사전 교육 이수 — 위생 교육, 근무지 수칙, 산업 안전</li>
                        <li>본인 정보 입력 — 앱에서 모국어로 직접 작성합니다</li>
                        <li>건강검진 결과 제출</li>
                        <li>가족 연락처 등록 — 긴급 상황 시 연락할 곳</li>
                    </ul>
                </div>
                <div class="nd-split__a nd-rise">
                    <div class="nd-plate nd-plate--photo">
                        <img class="nd-plate__img" src="{{ asset('site/assets/img/culture_class.jpg') }}" alt="출국 전 사전 교육 장면">
                        <span class="nd-plate__k">5</span>
                        <p class="nd-plate__t">출국 전 확인할 다섯 가지</p>
                        <p class="nd-plate__d">앱에서 모국어로 확인하고 그대로 제출할 수 있습니다.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 한국 생활 ==================== --}}
    <section class="nd-section nd-section--muted" id="living">
        <div class="nd-wrap">
            <div class="nd-sechead nd-rise">
                <span class="nd-eyebrow">Living in Korea</span>
                <h2 class="nd-h2">한국 생활 안내</h2>
                <p class="nd-lead">숙소와 급여, 그리고 아플 때 어떻게 하는지.</p>
            </div>

            <div class="nd-split" style="margin-bottom:56px">
                <div class="nd-split__a nd-rise">
                    <div class="nd-plate nd-plate--photo">
                        <img class="nd-plate__img" src="{{ asset('site/assets/img/sorting.jpg') }}" alt="선별 작업장 장면">
                        <span class="nd-plate__k">숙소 · 급여 · 건강</span>
                        <p class="nd-plate__t">첫 달이 가장 중요합니다</p>
                        <p class="nd-plate__d">모르는 것은 참지 말고 담당자에게 물어보십시오.</p>
                    </div>
                </div>
                <div class="nd-rise">
                    <h2 class="nd-h2">낯선 곳에서의 첫 달</h2>
                    <p class="nd-lead" style="margin-top:18px">
                        숙소·급여·건강 세 가지만 제대로 챙기면 나머지는 시간이 해결합니다.
                        아래 세 가지를 먼저 확인하십시오.
                    </p>
                </div>
            </div>

            <div class="nd-grid nd-grid--4">
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">숙소</span>
                    <h3 class="nd-h3">숙소</h3>
                    <p>농가가 제공하는 숙소에서 생활합니다. 입주 전 상태를 함께 확인하고 기록으로 남깁니다.</p>
                </article>
                {{-- 금액은 여기에 적지 말 것. 생활비·초기비용 숫자의 원본은 사전교육 안내
                     자료 한 곳이다(database/seeders/WorkerGuideSeeder.php, 앱 정보 화면).
                     두 곳에 적으면 한쪽만 고쳐져 반드시 어긋난다. --}}
                <article class="nd-card nd-card--accent nd-rise">
                    <span class="nd-card__no">비용</span>
                    <h3 class="nd-h3">본인 부담 비용</h3>
                    <p>숙소 사용료와 생활에서 쓰는 비용은 근로자가 부담합니다 — 식대, 난방비, 수도비, 전기비 등. 출국 전에 금액과 납부 방식을 확인하십시오.</p>
                </article>
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">급여</span>
                    <h3 class="nd-h3">급여</h3>
                    <p>본인 명의 계좌로 받습니다. 현금이나 타인 계좌로 받는 것은 정상적인 방식이 아닙니다.</p>
                </article>
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">건강</span>
                    <h3 class="nd-h3">건강 · 보험</h3>
                    <p>보험에 가입되어 있습니다. 다치거나 아프면 참지 말고 바로 담당자에게 알리십시오.</p>
                </article>
            </div>

            <div class="nd-split" style="margin-top:64px">
                <div class="nd-split__a nd-rise">
                    <div class="nd-plate nd-plate--photo">
                        <img class="nd-plate__img" src="{{ asset('site/assets/img/strawberry.jpg') }}" alt="수확한 작물을 선별·포장하는 작업">
                        <span class="nd-plate__k">4</span>
                        <p class="nd-plate__t">파종 · 관리 · 수확 · 선별</p>
                        <p class="nd-plate__d">배치받은 농가의 품목에 맞춰 필요한 작업을 배웁니다.</p>
                    </div>
                </div>
                <div class="nd-rise">
                    <h2 class="nd-h2">농업 현장 안내</h2>
                    <p class="nd-lead" style="margin-top:18px">
                        계절근로는 작물과 시기에 따라 하는 일이 달라집니다.
                        배치받은 농가의 품목에 맞춰 필요한 작업을 배우게 됩니다.
                    </p>
                    <ul class="nd-checks">
                        <li>파종 · 정식 — 모종을 옮겨 심는 작업</li>
                        <li>관리 — 물주기, 순치기, 병해충 확인</li>
                        <li>수확 — 작물별로 시기와 방법이 다릅니다</li>
                        <li>선별 · 포장 — 크기와 상태에 따라 나누는 작업</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 도움 요청 ==================== --}}
    <section class="nd-section nd-band">
        <div class="nd-wrap">
            <div class="nd-sechead nd-sechead--center nd-rise">
                <span class="nd-eyebrow">Support</span>
                <h2 class="nd-h2">도움이 필요할 때</h2>
                <p class="nd-lead">혼자 참지 마십시오. 모국어로 이야기할 수 있습니다.</p>
            </div>
            <div class="nd-grid nd-grid--3" style="margin-top:44px">
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">매달</span>
                    <h3 class="nd-h3">월별 인터뷰</h3>
                    <p>매달 담당자가 연락합니다. 급여, 차별, 건강, 생활 문제를 이때 이야기하십시오.</p>
                </article>
                <article class="nd-card nd-rise">
                    <span class="nd-card__no">민원</span>
                    <h3 class="nd-h3">민원 접수</h3>
                    <p>문의, 계약 연장, 조기 귀국 요청을 앱에서 접수할 수 있습니다.</p>
                </article>
                <article class="nd-card nd-card--accent nd-rise">
                    <span class="nd-card__no">SOS</span>
                    <h3 class="nd-h3">긴급 SOS</h3>
                    <p>사고나 위급한 상황에서는 앱의 SOS 버튼을 누르십시오. 즉시 담당자에게 전달됩니다.</p>
                </article>
            </div>
        </div>
    </section>

    {{-- ==================== FAQ ==================== --}}
    <section class="nd-section" id="faq">
        <div class="nd-wrap">
            <div class="nd-sechead nd-sechead--center nd-rise">
                <span class="nd-eyebrow">FAQ</span>
                <h2 class="nd-h2">자주 묻는 질문</h2>
            </div>

            <div class="nd-faq nd-rise" style="margin-top:40px">
                <details>
                    <summary>계절근로자로 얼마 동안 일할 수 있나요?</summary>
                    <div class="nd-faq__a">체류 기간은 배정받은 프로그램과 계약 조건에 따라 정해집니다. 정확한 기간은 계약서에 적힌 내용을 확인하십시오.</div>
                </details>
                <details>
                    <summary>급여는 어떻게 받나요?</summary>
                    <div class="nd-faq__a">본인 명의 계좌로 입금됩니다. 입국 후 통장 개설을 도와드립니다. 현금으로 받거나 다른 사람 계좌로 받는 것은 정상적인 방식이 아니므로 담당자에게 알려 주십시오.</div>
                </details>
                <details>
                    <summary>일하는 농가를 바꿀 수 있나요?</summary>
                    <div class="nd-faq__a">정해진 절차와 사유가 있는 경우에 한해 가능합니다. 먼저 담당자와 상담하십시오. 무단으로 이탈하면 체류 자격에 문제가 생깁니다.</div>
                </details>
                <details>
                    <summary>아프거나 다치면 어떻게 하나요?</summary>
                    <div class="nd-faq__a">즉시 농가와 담당자에게 알리십시오. 보험이 적용됩니다. 위급한 경우 앱의 SOS 버튼을 누르십시오.</div>
                </details>
                <details>
                    <summary>가족이나 형제와 함께 배치될 수 있나요?</summary>
                    <div class="nd-faq__a">함께 신청한 경우 같은 농가나 인근 농가로 묶어 배치하려 합니다. 다만 농가의 수요 조건에 따라 달라질 수 있습니다.</div>
                </details>
                <details>
                    <summary>한국어를 못 해도 괜찮나요?</summary>
                    <div class="nd-faq__a">안내와 알림은 모국어로 제공되며, 상담도 모국어로 가능합니다. 현장에서 필요한 작업 지시는 담당자가 모국어로 전달합니다.</div>
                </details>
            </div>
        </div>
    </section>

    <section class="nd-cta nd-band">
        <div class="nd-wrap">
            <h2 class="nd-h2">더 궁금한 점이 있으신가요</h2>
            <p class="nd-lead">담당자에게 직접 물어보실 수 있습니다.</p>
            <div class="nd-btnrow">
                <a class="nd-btn nd-btn--accent" href="{{ route('site.contact') }}">문의하기</a>
            </div>
        </div>
    </section>
@endsection
