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
@endphp
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>N.D.N Korea 운영 콘솔</title>
<link rel="preload" href="{{ asset('site/assets/fonts/PretendardVariable.woff2') }}" as="font" type="font/woff2" crossorigin>
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
<link rel="stylesheet" href="{{ asset('admin-assets/css/admin.css') }}">
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="sidebar__brand">
            <b>N.D.N</b><span>Console</span>
        </div>
        <nav class="sidebar__nav">
            @foreach ($menu as $group)
                <div class="nav-group">
                    @if ($group['group'] !== '')
                        <div class="nav-group__label">{{ $group['group'] }}</div>
                    @endif
                    @foreach ($group['items'] as $item)
                        <button type="button" class="nav-item"
                                data-screen="{{ $item['key'] }}"
                                data-title="{{ $item['label'] }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"
                                 stroke-linecap="round" stroke-linejoin="round">{!! $icons[$item['icon']] ?? '' !!}</svg>
                            <span>{{ $item['label'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="workspace">
        <header class="topbar">
            <div class="topbar__crumbs">N.D.N Korea 계절근로자 통합관리</div>
            <div class="topbar__user">
                <span class="topbar__avatar">{{ mb_substr($user->name, 0, 1) }}</span>
                <span>{{ $user->name }}</span>
                <form method="POST" action="{{ route('admin.logout') }}" style="margin:0">
                    @csrf
                    <button type="submit" class="btn-logout">로그아웃</button>
                </form>
            </div>
        </header>

        <div class="tabbar" id="tabbar"></div>
        <div class="tabpanes" id="tabpanes"></div>
    </div>
</div>

<script>
    window.NDN_ADMIN = {
        base: @json(rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/')),
        screenUrl: @json(url('admin/screen')),
        titles: @json($titles),
    };
</script>
<script src="{{ asset('admin-assets/js/admin.js') }}"></script>
</body>
</html>
