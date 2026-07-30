{{-- N.D.N Korea 회사소개 사이트 공통 레이아웃 --}}
@php
    // 활성 메뉴 키 (라우트에서 ['active' => '...'] 로 주입). 없으면 빈 문자열.
    $active ??= '';
    $nav = [
        'home'    => ['route' => 'site.home',    'label' => '홈'],
        'about'   => ['route' => 'site.about',   'label' => '회사소개'],
        'services'=> ['route' => 'site.services', 'label' => '서비스'],
        'worker'  => ['route' => 'site.worker',  'label' => '근로자 지원'],
        'partners'=> ['route' => 'site.partners', 'label' => '협력기관'],
        'contact' => ['route' => 'site.contact', 'label' => '문의'],
    ];
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'N.D.N Korea — 외국인 계절근로자 통합관리')</title>
<meta name="description" content="@yield('description', '주식회사 앤디앤(N.D.N Korea)은 외국인 계절근로자(E-8)의 전 주기를 하나의 체계로 운영합니다.')">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('site/assets/apple-touch-icon.png') }}">
<link rel="preload" href="{{ asset('site/assets/fonts/PretendardVariable.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('site/assets/css/style.css') }}">
<style>
    /* 언어 선택 — 국기가 아니라 언어 이름을 그 언어 문자로 보여 준다.
       국기는 언어가 아니다(영어를 쓰는 나라가 여럿이고, 방문자가 자기 국기를
       못 찾으면 고를 수 없다). 자기 언어 이름은 누구나 알아본다.

       넓은 화면은 6개를 한 줄로 펼치고, 좁은 화면은 드롭다운으로 접는다.
       6개를 헤더에 늘어놓으면 폭을 넘겨 페이지 전체가 가로로 밀린다. */
    .lang-switch{display:flex;gap:2px;align-items:center;margin-left:16px;flex:0 1 auto;min-width:0;}
    .lang-switch__item{display:inline-block;padding:4px 8px;border-radius:6px;font-size:13px;line-height:1.3;white-space:nowrap;color:#5B6472;text-decoration:none;transition:color .15s,background-color .15s;}
    .lang-switch__item:hover{color:#1A140F;background:rgba(15,23,42,.06);}
    .lang-switch__item.is-active{color:#0F7B4F;background:rgba(15,123,79,.12);font-weight:700;}

    /* 헤더 배경이 밝으므로 글자도 어두운색이어야 한다. 흰색으로 두면 빈 상자로 보인다. */
    .lang-select{display:none;flex:0 0 auto;margin-left:auto;max-width:46vw;
        padding:7px 28px 7px 10px;border:1px solid rgba(15,23,42,.18);border-radius:8px;
        background-color:#fff;color:#1A140F;font-size:14px;font-family:inherit;
        appearance:none;-webkit-appearance:none;cursor:pointer;
        background-image:url("data:image/svg+xml;charset=UTF-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%231A140F' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
        background-repeat:no-repeat;background-position:right 8px center;background-size:14px;}
    .lang-select:focus-visible{outline:2px solid #0F7B4F;outline-offset:2px;}

    @media (max-width:820px){
        .lang-switch{display:none;}
        .lang-select{display:block;}
    }
    /* 번역으로 라벨이 길어져도 상단 메뉴는 항상 한 줄 유지(데스크톱). 넘치면 가로 스크롤. */
    @media (min-width:821px){
        .header__inner{gap:16px;flex-wrap:nowrap;}
        .nav{flex-wrap:nowrap;min-width:0;overflow-x:auto;scrollbar-width:none;}
        .nav::-webkit-scrollbar{display:none;}
        .nav a{white-space:nowrap;}
    }
    @media (max-width:860px){.lang-switch{margin-left:auto;}}
</style>
@include('partials.tz-cookie')
</head>
<body>

<a class="skip-link" href="#main">본문 바로가기</a>

<header class="header">
    <div class="wrap header__inner">
        <a class="logo" href="{{ route('site.home') }}">
            <span class="logo__mark">N.D.N</span>
            <span class="logo__sub">Korea</span>
        </a>
        <nav class="nav" id="primary-nav" aria-label="주 메뉴">
            @foreach ($nav as $key => $item)
                <a href="{{ route($item['route']) }}" @if ($active === $key) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endforeach
        </nav>
        {{-- 언어 선택기 — 언어 이름을 그 언어 문자로 (번역 제외) --}}
        @php $curLocale = session('site_locale', 'ko'); @endphp
        {{-- 넓은 화면 — 6개를 한 줄로 --}}
        <div class="lang-switch" data-no-translate>
            @foreach (\App\Shared\Translation\SiteTranslator::NATIVE as $lc => $native)
                <a href="{{ route('site.lang', $lc) }}"
                   class="lang-switch__item @if ($curLocale === $lc) is-active @endif"
                   hreflang="{{ $lc }}" lang="{{ $lc }}"
                   @if ($curLocale === $lc) aria-current="true" @endif>{{ $native }}</a>
            @endforeach
        </div>
        {{-- 좁은 화면 — 드롭다운. 기기 기본 선택기라 6개가 모두 보인다. --}}
        <select class="lang-select" data-no-translate aria-label="Language"
                onchange="if(this.value)location.href=this.value">
            @foreach (\App\Shared\Translation\SiteTranslator::NATIVE as $lc => $native)
                <option value="{{ route('site.lang', $lc) }}" lang="{{ $lc }}"
                        @if ($curLocale === $lc) selected @endif>{{ $native }}</option>
            @endforeach
        </select>
        <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="primary-nav">
            <span class="sr-only">메뉴 열기</span>☰
        </button>
    </div>
</header>

<main id="main">
@yield('content')
</main>

<footer class="footer">
    <div class="wrap">
        <div class="footer__grid">
            <div class="footer__about">
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
        <div class="footer__bottom">
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
     이미 설치한 사람에게 "앱 설치" QR 을 보여 줄 이유가 없다.
     앱은 User-Agent 에 NDNApp 을 붙여 자신을 알린다. --}}
@unless (request()->userAgent() && str_contains(request()->userAgent(), 'NDNApp'))
    @include('partials.app-install-widget')
@endunless
@include('partials.chat-widget')

<script src="{{ asset('site/assets/js/main.js') }}"></script>
</body>
</html>
