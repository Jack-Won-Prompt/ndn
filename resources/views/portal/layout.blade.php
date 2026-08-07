@php
    use App\Domains\Settlement\Notifications\SettlementAssignedNotification;
    use App\Shared\Enums\UserRole;

    $user = auth()->user();
    $isPartner = $user?->isRole(UserRole::PartnerAgency) ?? false;
    $isFarm = $user?->isRole(UserRole::FarmOwner) ?? false;

    // 대리점에 새로 배정된 정착 건 — 메뉴에 숫자로 달아 둔다.
    $settleUnread = $isPartner
        ? $user->unreadNotifications()->where('type', SettlementAssignedNotification::class)->count()
        : 0;

    $active = $active ?? '';
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0B0F16">
<title>@yield('title', '협력 포털') — N.D.N Korea</title>
@include('partials.tz-cookie')
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
{{-- 로그인 화면과 같은 디자인 시스템. 로그인하고 들어왔는데 다른 사이트로 보이면
     안 된다. 셸(.nd-app*)은 ndn.css 에 이미 있던 것을 쓴다. --}}
<link rel="stylesheet" href="{{ asset('site/assets/css/ndn.css') }}?v={{ @filemtime(public_path('site/assets/css/ndn.css')) }}">
@stack('head')
</head>
<body>
<div class="nd-app">

    <header class="nd-app__bar">
        <div class="nd-wrap">
            <a class="nd-logo" href="{{ route('portal.index') }}">
                <span class="nd-logo__mark">N<i>.</i>D<i>.</i>N</span>
                <span class="nd-logo__sub">협력 포털</span>
            </a>

            <nav class="nd-app__nav">
                @if ($isFarm)
                    <a href="{{ route('demand.index') }}" class="{{ $active === 'demand' ? 'is-on' : '' }}">수요 신청</a>
                @endif
                @if ($isPartner)
                    <a href="{{ route('portal.settlements.index') }}" class="{{ $active === 'settlements' ? 'is-on' : '' }}">
                        정착 처리
                        @if ($settleUnread > 0)<span class="nd-app__badge">{{ $settleUnread }}</span>@endif
                    </a>
                @endif
                <a href="{{ route('portal.index') }}" class="{{ $active === 'chat' ? 'is-on' : '' }}">채팅</a>
            </nav>

            <div class="nd-app__end">
                <span class="nd-app__who">{{ $user->name }}</span>
                <form method="POST" action="{{ route('portal.logout') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="nd-app__out">로그아웃</button>
                </form>
            </div>
        </div>
    </header>

    <main class="nd-app__body">
        <div class="nd-wrap">
            @if (session('status'))
                <div class="nd-note nd-note--ok" style="margin-bottom:18px" role="status">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="nd-note nd-note--err" style="margin-bottom:18px" role="alert">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="nd-note nd-note--err" style="margin-bottom:18px" role="alert">
                    @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            @yield('body')
        </div>
    </main>
</div>
@stack('scripts')
</body>
</html>
