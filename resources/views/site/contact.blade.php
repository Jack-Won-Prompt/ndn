@extends('site.layout')

@section('title', '문의 — N.D.N Korea')
@section('description', 'N.D.N Korea 연락처 및 문의 양식.')

@section('content')

    <section class="nd-pagehero">
        <div class="nd-wrap">
            <p class="nd-crumb"><a href="{{ route('site.home') }}">홈</a><span>›</span>문의</p>
            <h1 class="nd-h1">문의</h1>
            <p class="nd-lead">지자체 담당자, 농가, 송출기관, 제휴사 모두 환영합니다.</p>
        </div>
    </section>

    <section class="nd-section">
        <div class="nd-wrap">
            <div class="nd-split" style="align-items:start">

                {{-- ----- 문의 양식 ----- --}}
                <div class="nd-rise">
                    <span class="nd-eyebrow">Inquiry</span>
                    <h2 class="nd-h2">문의 양식</h2>

                    <form class="nd-panel" style="margin-top:26px" data-nd-demoform novalidate>
                        <div class="nd-field">
                            <label for="f-type">문의 유형 <span class="nd-req" aria-hidden="true">*</span></label>
                            <select class="nd-select" id="f-type" name="type" required>
                                <option value="">선택하세요</option>
                                <option>지자체 — 프로그램 운영 문의</option>
                                <option>농가 — 인력 신청 문의</option>
                                <option>송출기관 — 협력 제안</option>
                                <option>제휴사 — 정착 서비스 대리점</option>
                                <option>기타</option>
                            </select>
                        </div>

                        <div class="nd-field">
                            <label for="f-org">기관 · 농가명 <span class="nd-req" aria-hidden="true">*</span></label>
                            <input class="nd-input" id="f-org" name="org" type="text" required autocomplete="organization">
                        </div>

                        <div class="nd-field">
                            <label for="f-name">담당자명 <span class="nd-req" aria-hidden="true">*</span></label>
                            <input class="nd-input" id="f-name" name="name" type="text" required autocomplete="name">
                        </div>

                        <div class="nd-field">
                            <label for="f-contact">연락처 <span class="nd-req" aria-hidden="true">*</span></label>
                            <input class="nd-input" id="f-contact" name="contact" type="text" required
                                   inputmode="tel" autocomplete="tel" placeholder="010-0000-0000">
                            <span class="nd-hint">회신 가능한 번호를 적어 주십시오.</span>
                        </div>

                        <div class="nd-field">
                            <label for="f-email">이메일</label>
                            <input class="nd-input" id="f-email" name="email" type="email" autocomplete="email">
                        </div>

                        <div class="nd-field">
                            <label for="f-msg">문의 내용 <span class="nd-req" aria-hidden="true">*</span></label>
                            <textarea class="nd-textarea" id="f-msg" name="message" required rows="5"
                                      placeholder="필요한 인원, 희망 시기, 품목 등을 적어 주시면 상담이 빨라집니다."></textarea>
                        </div>

                        <label class="nd-check" for="f-agree" style="align-items:flex-start;margin-bottom:20px">
                            <input id="f-agree" name="agree" type="checkbox" required style="margin-top:3px">
                            <span style="color:var(--nd-text-2);line-height:1.6">
                                문의 처리를 위한 개인정보 수집·이용에 동의합니다.
                                수집 항목은 담당자명·연락처·이메일이며, 문의 처리 완료 후 파기합니다.
                            </span>
                        </label>

                        <div class="nd-btnrow">
                            <button class="nd-btn nd-btn--ink" type="submit">문의 보내기</button>
                        </div>

                        <p class="nd-note" data-nd-formnote hidden tabindex="-1" style="margin-top:18px">
                            이 시안에서는 실제로 전송되지 않습니다. 입력하신 내용은 어디에도 저장되지 않았습니다.
                            바로 상담이 필요하시면 오른쪽 아래 <strong>문의하기</strong> 버튼을 이용해 주세요.
                        </p>
                    </form>
                </div>

                {{-- ----- 연락처 ----- --}}
                <div class="nd-rise">
                    <span class="nd-eyebrow">Contact</span>
                    <h2 class="nd-h2">연락처</h2>

                    <dl class="nd-info" style="margin-top:26px">
                        <div>
                            <dt>법인명</dt>
                            <dd>주식회사 앤디앤 (N.D.N Co., Ltd.)</dd>
                        </div>
                        <div>
                            <dt>주소</dt>
                            <dd>{{ $S['contact.address'] ?? '경기도 김포시 양촌읍 대곶남로580번길 55, 가동' }}</dd>
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

                    <div class="nd-plate nd-plate--photo" style="margin-top:32px">
                        <img class="nd-plate__img" src="{{ asset('site/assets/img/hero_greenhouse.jpg') }}" alt="온실 재배 현장">
                        <span class="nd-plate__k">24h</span>
                        <p class="nd-plate__t">실시간 상담은 지금 바로</p>
                        <p class="nd-plate__d">오른쪽 아래 문의하기 버튼을 누르면 담당자와 바로 대화할 수 있습니다.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>

    {{-- 근로자용 안내 --}}
    <section class="nd-cta nd-band">
        <div class="nd-wrap">
            <span class="nd-eyebrow">For Workers</span>
            <h2 class="nd-h2" style="margin-top:14px">근로자이신가요</h2>
            <p class="nd-lead">이 양식 말고 앱을 이용하십시오. 모국어로 상담할 수 있고, 긴급할 때는 SOS 버튼을 쓸 수 있습니다.</p>
            <div class="nd-btnrow">
                <a class="nd-btn nd-btn--onink" href="{{ route('site.worker') }}">근로자 지원 안내 보기</a>
            </div>
        </div>
    </section>
@endsection
