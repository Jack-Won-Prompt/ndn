<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', '화면') — NDN 콘솔</title>
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
<link rel="stylesheet" href="{{ asset('admin-assets/vendor/tui-grid/tui-pagination.css') }}">
<link rel="stylesheet" href="{{ asset('admin-assets/vendor/tui-grid/tui-grid.css') }}">
<link rel="stylesheet" href="{{ asset('admin-assets/vendor/wwgrid/wwGrid.css') }}?v={{ @filemtime(public_path('admin-assets/vendor/wwgrid/wwGrid.css')) }}">
<link rel="stylesheet" href="{{ asset('admin-assets/css/ui.css') }}?v={{ @filemtime(public_path('admin-assets/css/ui.css')) }}">
<link rel="stylesheet" href="{{ asset('admin-assets/css/embed.css') }}?v={{ @filemtime(public_path('admin-assets/css/embed.css')) }}">
</head>
<body>
<div class="screen">
    @yield('content')
</div>

{{-- iframe 안에서 부모 셸에 "탭 열기"를 요청하는 헬퍼 --}}
<script>
    function ndnOpenTab(key, title) {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ ndnOpenTab: true, key: key, title: title }, '*');
        }
    }
</script>
<script src="{{ asset('admin-assets/js/ui.js') }}?v={{ @filemtime(public_path('admin-assets/js/ui.js')) }}"></script>
@hasSection('grid')
    <script src="{{ asset('admin-assets/vendor/tui-grid/tui-pagination.js') }}"></script>
    <script src="{{ asset('admin-assets/vendor/tui-grid/tui-grid.js') }}"></script>
    <script src="{{ asset('admin-assets/js/grid.js') }}?v={{ @filemtime(public_path('admin-assets/js/grid.js')) }}"></script>
    @yield('grid')
@endif
@hasSection('wwgrid')
    <script src="{{ asset('admin-assets/vendor/wwgrid/wwGrid.js') }}?v={{ @filemtime(public_path('admin-assets/vendor/wwgrid/wwGrid.js')) }}"></script>
    <script src="{{ asset('admin-assets/js/wwconsole.js') }}?v={{ @filemtime(public_path('admin-assets/js/wwconsole.js')) }}"></script>
    @yield('wwgrid')
@endif
@yield('script')
</body>
</html>
