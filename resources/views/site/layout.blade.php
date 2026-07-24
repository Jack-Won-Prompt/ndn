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
<title>@yield('title', 'N.D.N Korea — 외국인 계절근로자 통합관리')</title>
<meta name="description" content="@yield('description', '주식회사 앤디앤(N.D.N Korea)은 외국인 계절근로자(E-8)의 전 주기를 하나의 체계로 운영합니다.')">
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('site/assets/apple-touch-icon.png') }}">
<link rel="preload" href="{{ asset('site/assets/fonts/PretendardVariable.woff2') }}" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="{{ asset('site/assets/css/style.css') }}">
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
                    <li><a href="{{ route('site.worker') }}">입국 전 준비</a></li>
                    <li><a href="{{ route('site.worker') }}#living">한국 생활 안내</a></li>
                    <li><a href="{{ route('site.worker') }}#faq">자주 묻는 질문</a></li>
                </ul>
            </div>
        </div>
        <div class="footer__bottom">
            <span>&copy; {{ date('Y') }} 주식회사 앤디앤 (N.D.N Korea)</span>
            <nav aria-label="약관">
                <a href="#">개인정보처리방침</a>
                <a href="#">이용약관</a>
            </nav>
        </div>
    </div>
</footer>

<script src="{{ asset('site/assets/js/main.js') }}"></script>
</body>
</html>
