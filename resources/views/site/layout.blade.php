{{-- N.D.N Korea 회사소개 사이트 공통 레이아웃 (2026 리디자인) --}}
@php
    // 활성 메뉴 키 (SiteController 에서 ['active' => '...'] 로 주입). 없으면 빈 문자열.
    $active ??= '';

    // 모바일 앱의 WebView 안에서 보고 있는지. 앱이 User-Agent 에 NDNApp 을 붙인다.
    // 앱에는 자체 버튼이 떠 있어 화면 아래쪽 배치가 달라진다.
    $inApp = str_contains((string) request()->userAgent(), 'NDNApp');

    $nav = [
        'home'     => ['route' => 'site.home',     'label' => '홈'],
        'about'    => ['route' => 'site.about',    'label' => '회사소개'],
        'services' => ['route' => 'site.services', 'label' => '서비스'],
        'worker'   => ['route' => 'site.worker',   'label' => '근로자 지원'],
        'partners' => ['route' => 'site.partners', 'label' => '협력기관'],
        'contact'  => ['route' => 'site.contact',  'label' => '문의'],
    ];

    $curLocale = session('site_locale', 'ko');
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0B0F16">
<title>@yield('title', 'N.D.N Korea — 외국인 계절근로자 통합관리')</title>
<meta name="description" content="@yield('description', '주식회사 앤디앤(N.D.N Korea)은 외국인 계절근로자(E-8)의 전 주기를 하나의 체계로 운영합니다.')">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('site/assets/apple-touch-icon.png') }}">
<link rel="preload" href="{{ asset('site/assets/fonts/PretendardVariable.woff2') }}" as="font" type="font/woff2" crossorigin>
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
{{-- 과도기: 아직 새 시스템으로 옮기지 않은 페이지(회사소개·서비스·근로자지원·협력기관·
     문의·약관·방침·계정삭제)가 옛 클래스를 쓴다. 그 페이지들을 다 옮기면 이 줄과
     public/site/assets/css/style.css, js/main.js 를 함께 지운다.
     클래스 이름이 겹치지 않아(새 시스템은 전부 nd- 접두) 서로 간섭하지 않는다. --}}
<link rel="stylesheet" href="{{ asset('site/assets/css/style.css') }}?v={{ @filemtime(public_path('site/assets/css/style.css')) }}">
<link rel="stylesheet" href="{{ asset('site/assets/css/ndn.css') }}?v={{ @filemtime(public_path('site/assets/css/ndn.css')) }}">
{{-- 등장 효과는 JS 가 있을 때만 건다. 이 줄이 없으면 스크립트가 실패한 환경에서
     본문이 투명한 채로 남는다. 첫 페인트 전에 실행되어야 하므로 인라인이다. --}}
<script>document.documentElement.className += ' nd-js';</script>
@include('partials.tz-cookie')
@stack('head')
</head>
<body>

<a class="nd-skip" href="#main">본문 바로가기</a>

<header class="nd-header" data-nd-header>
    <div class="nd-wrap nd-header__in">
        <a class="nd-logo" href="{{ route('site.home') }}" aria-label="N.D.N Korea 홈">
            <img class="nd-logo__img" src="{{ asset('site/assets/logo.png') }}?v={{ @filemtime(public_path('site/assets/logo.png')) }}"
                 alt="N.D.N" width="45" height="30">
            <span class="nd-logo__sub">Korea</span>
        </a>

        <nav class="nd-nav" id="nd-nav" aria-label="주 메뉴">
            @foreach ($nav as $key => $item)
                <a href="{{ route($item['route']) }}" @if ($active === $key) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="nd-header__end">
            {{-- 언어 선택 — 언어 이름을 그 언어 문자로. 번역기가 건드리면 안 되므로 제외 표시. --}}
            <div class="nd-lang" data-no-translate>
                @foreach (\App\Shared\Translation\SiteTranslator::NATIVE as $lc => $native)
                    <a href="{{ route('site.lang', $lc) }}"
                       class="@if ($curLocale === $lc) is-on @endif"
                       hreflang="{{ $lc }}" lang="{{ $lc }}"
                       @if ($curLocale === $lc) aria-current="true" @endif>{{ $native }}</a>
                @endforeach
            </div>

            {{-- 좁은 화면 — 기기 기본 선택기라 6개가 모두 보인다. --}}
            <select class="nd-langsel" data-no-translate aria-label="Language"
                    onchange="if(this.value)location.href=this.value">
                @foreach (\App\Shared\Translation\SiteTranslator::NATIVE as $lc => $native)
                    <option value="{{ route('site.lang', $lc) }}" lang="{{ $lc }}"
                            @if ($curLocale === $lc) selected @endif>{{ $native }}</option>
                @endforeach
            </select>

            <button class="nd-burger" type="button" data-nd-burger
                    aria-expanded="false" aria-controls="nd-nav">
                <span></span><span class="nd-sr">메뉴 열기</span>
            </button>
        </div>
    </div>
</header>

<main id="main">
@yield('content')
</main>

<footer class="nd-footer">
    <div class="nd-wrap">
        <div class="nd-footer__grid">
            <div class="nd-footer__about">
                <h4>N.D.N Korea</h4>
                <p>
                    주식회사 앤디앤. 외국인 계절근로자(E-8)의 모집부터 귀국까지
                    전 주기를 하나의 체계로 운영합니다.
                </p>
            </div>
            <div>
                <h4>회사</h4>
                <ul>
                    <li><a href="{{ route('site.about') }}">회사소개</a></li>
                    <li><a href="{{ route('site.partners') }}">협력기관</a></li>
                    <li><a href="{{ route('site.contact') }}">문의</a></li>
                </ul>
            </div>
            <div>
                <h4>서비스</h4>
                <ul>
                    <li><a href="{{ route('site.services') }}">모집 &amp; 선별</a></li>
                    <li><a href="{{ route('site.services') }}#education">교육 서비스</a></li>
                    <li><a href="{{ route('site.services') }}#management">현장 관리</a></li>
                </ul>
            </div>
            <div>
                <h4>근로자</h4>
                <ul>
                    <li><a href="{{ route('app.download') }}">모바일 앱 설치</a></li>
                    <li><a href="{{ route('site.worker') }}">입국 전 준비</a></li>
                    <li><a href="{{ route('site.worker') }}#living">한국 생활 안내</a></li>
                    <li><a href="{{ route('site.worker') }}#faq">자주 묻는 질문</a></li>
                </ul>
            </div>
        </div>
        <div class="nd-footer__bottom">
            <span>&copy; {{ date('Y') }} 주식회사 앤디앤 (N.D.N Korea)</span>
            <nav aria-label="약관">
                <a href="{{ route('site.privacy') }}">개인정보처리방침</a>
                <a href="{{ route('site.terms') }}">이용약관</a>
                <a href="{{ route('legal.account-deletion') }}">계정 삭제</a>
            </nav>
        </div>
    </div>
</footer>

{{-- 앱 안(WebView)에서 볼 때는 설치 안내를 띄우지 않는다 —
     이미 설치한 사람에게 "앱 설치" QR 을 보여 줄 이유가 없다. --}}
@unless ($inApp)
    @include('partials.app-install-widget')
@endunless
@include('partials.chat-widget')

@if ($inApp)
    {{-- 앱은 오른쪽 아래에 '로그인' 버튼을 띄운다. 문의 위젯이 기본 위치에
         있으면 그 버튼과 겹치므로 위로 올린다. 순서상 위젯 스타일 뒤에 와야
         모바일 미디어쿼리(bottom:12px)까지 덮는다. --}}
    <style>
        .cw { --cw-bottom: 92px; }
        @media (max-width: 640px) { .cw { --cw-bottom: 84px; } }
    </style>
@endif

<script src="{{ asset('site/assets/js/ndn.js') }}?v={{ @filemtime(public_path('site/assets/js/ndn.js')) }}"></script>
@stack('scripts')
</body>
</html>
