@php
    // 사이드바 아이콘 (인라인 SVG, 외부 의존 없음)
    $icons = [
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9z"/><path d="M8 11h8M8 15h5"/>',
        'users'     => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.5a3 3 0 0 1 0 5.8M15.5 20a5.5 5.5 0 0 1 5-4"/>',
        'inbox'     => '<path d="M4 13l2.5-8h11L20 13"/><path d="M4 13v5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-5"/><path d="M4 13h4l1.5 2.5h5L16 13h4"/>',
        'cog'       => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1l2.1-2.1M17 7l2.1-2.1"/>',
    ];
    $titles = [];
    foreach ($menu as $g) { foreach ($g['items'] as $it) { $titles[$it['key']] = $it['label']; } }
    // 사이드바에 없고 상단 버튼으로만 여는 화면 — 탭 복원 시 제목이 키로 나오지 않게 등록한다.
    $titles['service-requests'] = 'SR';
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>N.D.N Korea 운영 콘솔</title>
@include('partials.tz-cookie')
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<link rel="apple-touch-icon" href="{{ asset('site/assets/apple-touch-icon.png') }}">
<link rel="preload" href="{{ asset('site/assets/fonts/PretendardVariable.woff2') }}" as="font" type="font/woff2" crossorigin>
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
    .nav-item{position:relative;}
    .nav-badge{margin-left:auto;min-width:18px;height:18px;padding:0 5px;border-radius:9px;background:#E5484D;color:#fff;font-size:11px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;line-height:1;}
    .nav-badge[hidden]{display:none;}
    /* 상단 SR 버튼 — 어느 화면에서든 서비스 요청을 열 수 있게 한다 */
    .topbar__sr{display:inline-flex;align-items:center;gap:6px;margin-right:14px;padding:5px 11px;
        font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;
        background:#fff;color:var(--mv2-text-strong,#0F172A);
        border:1px solid var(--mv2-border-default,#D9DEE7);border-radius:100px;}
    .topbar__sr:hover{background:var(--mv2-slate-25,#F6F8FB);}
    .topbar__sr svg{width:16px;height:16px;}
</style>
<link rel="stylesheet" href="{{ asset('admin-assets/css/ui.css') }}?v={{ @filemtime(public_path('admin-assets/css/ui.css')) }}">
<link rel="stylesheet" href="{{ asset('admin-assets/css/admin.css') }}?v={{ @filemtime(public_path('admin-assets/css/admin.css')) }}">
<style>
    .deploy-warn{background:#FDECEC;border-bottom:1px solid #F5C2C0;color:#8A1F1C;
        padding:11px 22px;font-size:13px;line-height:1.7;}
    .deploy-warn b{font-weight:800;margin-right:6px;}
    .deploy-warn span{margin-right:6px;}
    .deploy-warn code{background:#fff;border:1px solid #F0C9C7;border-radius:4px;padding:1px 6px;font-size:12.5px;}
</style>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="sidebar__brand">
            <b>N.D.N</b><span>Console</span>
        </div>
        <nav class="sidebar__nav">
            @foreach ($menu as $group)
                {{-- 그룹이 6개 22항목으로 늘어 한 화면에 다 들어가지 않는다. 접을 수 있게 한다.
                     펼침 상태는 localStorage 에 남겨 다음 접속에도 유지된다(console.js). --}}
                <div class="nav-group" data-group="{{ $group['group'] }}">
                    @if ($group['group'] !== '')
                        <button type="button" class="nav-group__label" data-group-toggle
                                aria-expanded="true">
                            <span>{{ $group['group'] }}</span>
                            <svg class="nav-group__chev" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M6 9l6 6 6-6"/>
                            </svg>
                        </button>
                    @endif
                    <div class="nav-group__items">
                    @foreach ($group['items'] as $item)
                        @php $bc = ($badges ?? [])[$item['key']] ?? 0; @endphp
                        <button type="button" class="nav-item"
                                data-screen="{{ $item['key'] }}"
                                data-title="{{ $item['label'] }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round">{!! $icons[$item['icon']] ?? '' !!}</svg>
                            <span>{{ $item['label'] }}</span>
                            <span class="nav-badge" data-badge-for="{{ $item['key'] }}" {{ $bc > 0 ? '' : 'hidden' }}>{{ $bc }}</span>
                        </button>
                    @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="workspace">
        <header class="topbar">
            <div class="topbar__crumbs">N.D.N Korea 계절근로자 통합관리</div>
            <div class="topbar__user">
                {{-- SR(서비스 요청) — 시스템 개선·오류 요청 창구. 어느 화면에서든 바로 열 수 있게 상단에 둔다. --}}
                <button type="button" class="topbar__sr" id="sr-open" title="SR · 서비스 요청">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3a9 9 0 0 0-9 9v4.5A2.5 2.5 0 0 0 5.5 19H7v-6H5v-1a7 7 0 0 1 14 0v1h-2v6h1.5A2.5 2.5 0 0 0 21 16.5V12a9 9 0 0 0-9-9z"/>
                    </svg>
                    <span>SR</span>
                </button>
                <span class="topbar__avatar">{{ mb_substr($user->name, 0, 1) }}</span>
                <span>{{ $user->name }}</span>
                <form method="POST" action="{{ route('admin.logout') }}" style="margin:0" id="logout-form">
                    @csrf
                    <button type="submit" class="btn-logout">로그아웃</button>
                </form>
            </div>
        </header>

        {{-- 배포가 덜 끝난 상태를 눈에 보이게 한다. 같은 원인으로 장애 보고가
             세 번 왔는데, 그때마다 증상만 달랐고 원인은 어디에도 드러나지 않았다. --}}
        @if (! empty($deployProblems))
            <div class="deploy-warn">
                <b>배포가 덜 끝났습니다.</b>
                @foreach ($deployProblems as $p)<span>{{ $p }}</span>@endforeach
                <code>php artisan migrate --force</code> 를 먼저, 그다음 <code>php artisan optimize</code> 를 실행하세요.
                순서를 바꾸면 라우트만 살아나고 테이블이 없어 다른 이유로 500 이 납니다.
            </div>
        @endif

        <div class="tabbar" id="tabbar"></div>
        <div class="tabpanes" id="tabpanes"></div>
    </div>
</div>

<script>
    window.NDN_ADMIN = {
        base: @json(rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/')),
        screenUrl: @json(url('admin/screen')),
        titles: @json($titles),
        pusherKey: @json(config('broadcasting.connections.pusher.key')),
        pusherCluster: @json(config('broadcasting.connections.pusher.options.cluster')),
        broadcastAuth: @json(url('broadcasting/auth')),
    };
</script>
<script src="{{ asset('admin-assets/js/ui.js') }}?v={{ @filemtime(public_path('admin-assets/js/ui.js')) }}"></script>
<script src="{{ asset('admin-assets/js/admin.js') }}?v={{ @filemtime(public_path('admin-assets/js/admin.js')) }}"></script>
<script src="{{ asset('admin-assets/vendor/pusher/pusher.min.js') }}"></script>
<script>
    // 사이드바 메뉴 배지 유틸 + Pusher 실시간 알림 (주요 이벤트)
    (function () {
        function setBadge(key, n) {
            var el = document.querySelector('.nav-badge[data-badge-for="' + key + '"]');
            if (!el) return;
            if (n > 0) { el.textContent = n; el.hidden = false; } else { el.hidden = true; }
            // 그룹이 접혀 있으면 머리글 점으로만 보인다. 함께 갱신한다.
            if (window.ndnSyncGroupBadges) window.ndnSyncGroupBadges();
        }
        function bumpBadge(key) {
            var el = document.querySelector('.nav-badge[data-badge-for="' + key + '"]');
            if (!el) return;
            setBadge(key, (parseInt(el.textContent, 10) || 0) + 1);
        }
        window.ndnSetBadge = setBadge;

        // 화면(iframe)에서 "읽음" 처리 시 배지를 0으로 내릴 수 있도록 메시지 수신
        window.addEventListener('message', function (e) {
            if (e.data && e.data.ndnBadge) setBadge(e.data.ndnBadge.key, e.data.ndnBadge.count);
        });

        var A = window.NDN_ADMIN;
        if (!A.pusherKey || typeof Pusher === 'undefined') return;   // Pusher 미설정 → 서버 렌더 배지만 사용
        var token = document.querySelector('meta[name="csrf-token"]').content;
        try {
            var pusher = new Pusher(A.pusherKey, {
                cluster: A.pusherCluster,
                forceTLS: true,
                authEndpoint: A.broadcastAuth,
                auth: { headers: { 'X-CSRF-TOKEN': token } },
            });
            pusher.subscribe('private-admin.alerts').bind('admin.alert', function (data) {
                if (window.ndnToast) ndnToast(data.message, { type: data.kind === 'sos' ? 'error' : 'info' });
                if (data.screen) bumpBadge(data.screen);
            });
        } catch (err) { /* 실시간 실패해도 콘솔은 정상 동작 */ }
    })();
</script>
<script>
    // 상단 SR 버튼 → SR 화면을 탭으로 연다.
    // admin.js 의 openTab 은 모듈 안에 있어 직접 부를 수 없으므로, iframe 화면들이 쓰는
    // 같은 postMessage 경로를 자기 자신에게 보낸다.
    (function () {
        var btn = document.getElementById('sr-open');
        if (!btn) return;
        btn.addEventListener('click', function () {
            window.postMessage({ ndnOpenTab: true, key: 'service-requests', title: 'SR' }, '*');
        });
    })();
</script>
<script>
    // 로그아웃 확인 — 커스텀 모달
    (function () {
        var form = document.getElementById('logout-form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            if (form.dataset.confirmed) return;   // 확인 후 실제 제출
            e.preventDefault();
            ndnConfirm('운영 콘솔에서 로그아웃하시겠습니까?', {
                title: '로그아웃',
                okText: '로그아웃',
                cancelText: '취소',
                danger: true,
            }).then(function (ok) {
                if (ok) { form.dataset.confirmed = '1'; form.submit(); }
            });
        });
    })();
</script>
</body>
</html>
