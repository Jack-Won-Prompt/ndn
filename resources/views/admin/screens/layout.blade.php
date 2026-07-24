<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', '화면') — NDN 콘솔</title>
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
</style>
<link rel="stylesheet" href="{{ asset('admin-assets/css/embed.css') }}">
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
@yield('script')
</body>
</html>
