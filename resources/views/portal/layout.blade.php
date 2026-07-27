@php
    $user = auth()->user();
    $isPartner = $user?->isRole(\App\Shared\Enums\UserRole::PartnerAgency) ?? false;
    $isFarm = $user?->isRole(\App\Shared\Enums\UserRole::FarmOwner) ?? false;
    $settleUnread = $isPartner
        ? $user->unreadNotifications()
            ->where('type', \App\Domains\Settlement\Notifications\SettlementAssignedNotification::class)
            ->count()
        : 0;
    $active = $active ?? '';
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', '협력 포털') — N.D.N Korea</title>
@include('partials.tz-cookie')
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
    body{margin:0;font-family:"Pretendard Variable","Pretendard",-apple-system,"Malgun Gothic",sans-serif;background:#F6F8F8;color:#1B1E24;-webkit-font-smoothing:antialiased;}
    .portal-top{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 22px;border-bottom:1px solid #E3E6EA;background:#fff;}
    .portal-brand b{font-size:18px;font-weight:800;letter-spacing:.04em;}
    .portal-brand span{font-size:12px;color:#6B7280;margin-left:8px;letter-spacing:.1em;text-transform:uppercase;}
    .portal-left{display:flex;align-items:center;}
    .portal-nav{display:flex;align-items:center;gap:6px;margin-left:26px;}
    .portal-nav a{position:relative;font-size:14px;color:#4B5563;text-decoration:none;padding:7px 14px;border-radius:8px;font-weight:600;}
    .portal-nav a:hover{background:#EEF1F4;color:#1B1E24;}
    .portal-nav a.is-active{background:#1E9C92;color:#fff;}
    .portal-badge{display:inline-flex;min-width:17px;height:17px;padding:0 5px;margin-left:6px;border-radius:9px;background:#E5484D;color:#fff;font-size:11px;font-weight:700;align-items:center;justify-content:center;}
    .portal-user{display:flex;align-items:center;gap:12px;font-size:13px;color:#333A44;}
    .portal-user form{margin:0;}
    .portal-logout{font-family:inherit;font-size:13px;color:#6B7280;background:none;border:1px solid #E3E6EA;border-radius:6px;padding:6px 12px;cursor:pointer;}
    .portal-logout:hover{color:#1B1E24;border-color:#9AA1AC;}
    .portal-body{padding:22px;max-width:1100px;margin:0 auto;}
    .pflash{padding:11px 15px;border-radius:9px;font-size:14px;margin-bottom:16px;}
    .pflash--ok{background:#E7F3F1;color:#12695F;border:1px solid #B9E0D9;}
    .pflash--err{background:#FDECEC;color:#B42318;border:1px solid #F3C0C0;}
</style>
@stack('head')
</head>
<body>
    <header class="portal-top">
        <div class="portal-left">
            <div class="portal-brand"><b>N.D.N</b><span>협력 포털</span></div>
            <nav class="portal-nav">
                @if ($isFarm)
                    <a href="{{ route('demand.index') }}">수요 신청</a>
                @endif
                @if ($isPartner)
                    <a href="{{ route('portal.settlements.index') }}" class="{{ $active === 'settlements' ? 'is-active' : '' }}">
                        정착 처리
                        @if ($settleUnread > 0)<span class="portal-badge">{{ $settleUnread }}</span>@endif
                    </a>
                @endif
                <a href="{{ route('portal.index') }}" class="{{ $active === 'chat' ? 'is-active' : '' }}">채팅</a>
            </nav>
        </div>
        <div class="portal-user">
            <span>{{ $user->name }}</span>
            <form method="POST" action="{{ route('portal.logout') }}">
                @csrf
                <button type="submit" class="portal-logout">로그아웃</button>
            </form>
        </div>
    </header>

    <main class="portal-body">
        @if (session('status'))
            <div class="pflash pflash--ok">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="pflash pflash--err">{{ session('error') }}</div>
        @endif
        @yield('body')
    </main>
    @stack('scripts')
</body>
</html>
