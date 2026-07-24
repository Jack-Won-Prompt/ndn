@extends('site.layout')

@section('title', '문의 — N.D.N Korea')
@section('description', 'N.D.N Korea 연락처 및 문의 양식.')

@section('content')


    <section class="page-head">
        <div class="wrap page-head__inner">
            <p class="crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>문의</p>
            <h1>문의</h1>
            <p>지자체 담당자, 농가, 송출기관, 제휴사 모두 환영합니다.</p>
        </div>
    </section>

    <section class="section">
        <div class="wrap">
            <div class="split" style="align-items:start">

                <!-- ----- 폼 ----- -->
                <div>
                    <span class="eyebrow">Inquiry</span>
                    <div class="rule"></div>
                    <h2>문의 양식</h2>

                    <form class="form" data-demo-form novalidate>
                        <div class="field">
                            <label for="f-type">문의 유형 <span class="req" aria-hidden="true">*</span></label>
                            <select id="f-type" name="type" required>
                                <option value="">선택하세요</option>
                                <option>지자체 — 프로그램 운영 문의</option>
                                <option>농가 — 인력 신청 문의</option>
                                <option>송출기관 — 협력 제안</option>
                                <option>제휴사 — 정착 서비스 대리점</option>
                                <option>기타</option>
                            </select>
                        </div>

                        <div class="field">
                            <label for="f-org">기관 · 농가명 <span class="req" aria-hidden="true">*</span></label>
                            <input id="f-org" name="org" type="text" required autocomplete="organization">
                        </div>

                        <div class="field">
                            <label for="f-name">담당자명 <span class="req" aria-hidden="true">*</span></label>
                            <input id="f-name" name="name" type="text" required autocomplete="name">
                        </div>

                        <div class="field">
                            <label for="f-contact">연락처 <span class="req" aria-hidden="true">*</span></label>
                            <input id="f-contact" name="contact" type="text" required
                                   inputmode="tel" autocomplete="tel" placeholder="010-0000-0000">
                            <span class="field__hint">회신 가능한 번호를 적어 주십시오.</span>
                        </div>

                        <div class="field">
                            <label for="f-email">이메일</label>
                            <input id="f-email" name="email" type="email" autocomplete="email">
                        </div>

                        <div class="field">
                            <label for="f-msg">문의 내용 <span class="req" aria-hidden="true">*</span></label>
                            <textarea id="f-msg" name="message" required
                                      placeholder="필요한 인원, 희망 시기, 품목 등을 적어 주시면 상담이 빨라집니다."></textarea>
                        </div>

                        <label class="consent" for="f-agree">
                            <input id="f-agree" name="agree" type="checkbox" required>
                            <span>
                                문의 처리를 위한 개인정보 수집·이용에 동의합니다.
                                수집 항목은 담당자명·연락처·이메일이며, 문의 처리 완료 후 파기합니다.
                            </span>
                        </label>

                        <div class="btn-row">
                            <button class="btn btn--dark" type="submit">문의 보내기</button>
                        </div>

                        <p class="consent" data-form-note hidden tabindex="-1"
                           style="border-left:3px solid var(--ink)">
                            이 시안에서는 실제로 전송되지 않습니다. 입력하신 내용은 어디에도 저장되지 않았습니다.
                        </p>
                    </form>
                </div>

                <!-- ----- 연락처 ----- -->
                <div>
                    <span class="eyebrow">Contact</span>
                    <div class="rule"></div>
                    <h2>연락처</h2>

                    <dl class="info-list" style="margin-top:30px">
                        <div>
                            <dt>법인명</dt>
                            <dd>주식회사 앤디앤 (N.D.N Co., Ltd.)</dd>
                        </div>
                        <div>
                            <dt>주소</dt>
                            <dd>{{ $S['contact.address'] ?? '○○도 ○○시 ○○로 ○○' }}</dd>
                        </div>
                        <div>
                            <dt>대표전화</dt>
                            <dd>{{ $S['contact.phone'] ?? '○○-○○○○-○○○○' }}</dd>
                        </div>
                        <div>
                            <dt>이메일</dt>
                            <dd>{{ $S['contact.email'] ?? '○○○@○○○.co.kr' }}</dd>
                        </div>
                        <div>
                            <dt>운영 시간</dt>
                            <dd>{{ $S['contact.hours'] ?? '평일 ○○:○○ – ○○:○○' }}</dd>
                        </div>
                    </dl>

                    <div class="photo photo--wide" style="margin-top:36px">
                        <img src="{{ asset('site/assets/img/hero_greenhouse.jpg') }}" alt="지도 자리표시자">
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- 근로자용 안내 -->
    <section class="section section--dark">
        <div class="wrap">
            <div class="sec-head sec-head--center" style="margin-bottom:36px">
                <span class="eyebrow">For Workers</span>
                <div class="rule"></div>
                <h2>근로자이신가요</h2>
                <p>이 양식 말고 앱을 이용하십시오. 모국어로 상담할 수 있고, 긴급할 때는 SOS 버튼을 쓸 수 있습니다.</p>
            </div>
            <div class="btn-row" style="justify-content:center">
                <a class="btn btn--ghost" href="{{ route('site.worker') }}">근로자 지원 안내 보기</a>
            </div>
        </div>
    </section>
@endsection
