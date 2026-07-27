<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', '수요 신청') — N.D.N 협력 포털</title>
@include('partials.tz-cookie')
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
    *{box-sizing:border-box;}
    body{margin:0;font-family:"Pretendard Variable","Pretendard",-apple-system,"Malgun Gothic",sans-serif;background:#F4F6F8;color:#1B1E24;-webkit-font-smoothing:antialiased;}
    .dp-top{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 22px;border-bottom:1px solid #E3E6EA;background:#fff;}
    .dp-brand b{font-size:18px;font-weight:800;letter-spacing:.04em;}
    .dp-brand span{font-size:12px;color:#6B7280;margin-left:8px;letter-spacing:.1em;text-transform:uppercase;}
    .dp-nav{display:flex;align-items:center;gap:6px;margin-left:26px;}
    .dp-nav a{font-size:14px;color:#4B5563;text-decoration:none;padding:7px 14px;border-radius:8px;font-weight:600;}
    .dp-nav a:hover{background:#EEF1F4;color:#1B1E24;}
    .dp-nav a.is-active{background:#1E9C92;color:#fff;}
    .dp-left{display:flex;align-items:center;}
    .dp-user{display:flex;align-items:center;gap:12px;font-size:13px;color:#333A44;}
    .dp-user form{margin:0;}
    .dp-logout{font-family:inherit;font-size:13px;color:#6B7280;background:none;border:1px solid #E3E6EA;border-radius:6px;padding:6px 12px;cursor:pointer;}
    .dp-logout:hover{color:#1B1E24;border-color:#9AA1AC;}
    .dp-body{max-width:960px;margin:0 auto;padding:26px 22px;}
    .dp-flash{background:#E7F6EC;color:#1B7F43;border:1px solid #B6E3C6;border-radius:10px;padding:12px 16px;font-size:14px;margin-bottom:18px;}
    .dp-err{background:#FDECEC;color:#B42318;border:1px solid #F5C2C0;border-radius:10px;padding:12px 16px;font-size:14px;margin-bottom:18px;}
    .dp-err ul{margin:0;padding-left:18px;}
    .dp-card{background:#fff;border:1px solid #E3E6EA;border-radius:14px;box-shadow:0 1px 2px rgba(15,23,42,.04);overflow:hidden;}
    .dp-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
    .dp-head h1{font-size:22px;margin:0;}
    .dp-btn{display:inline-flex;align-items:center;gap:6px;font-family:inherit;font-size:14px;font-weight:700;background:#1E9C92;color:#fff;border:0;border-radius:8px;padding:10px 18px;cursor:pointer;text-decoration:none;}
    .dp-btn:hover{background:#178578;}
    .dp-btn--ghost{background:#fff;color:#1E9C92;border:1px solid #1E9C92;}
    .dp-btn--ghost:hover{background:#E9F6F4;}
</style>
</head>
<body>
    <header class="dp-top">
        <div class="dp-left">
            <div class="dp-brand"><b>N.D.N</b><span>협력 포털</span></div>
            <nav class="dp-nav">
                <a href="{{ route('demand.index') }}" class="is-active">수요 신청</a>
                <a href="{{ route('portal.index') }}">채팅</a>
            </nav>
        </div>
        <div class="dp-user">
            <span>{{ auth()->user()?->name }}</span>
            <form method="POST" action="{{ route('portal.logout') }}">@csrf
                <button type="submit" class="dp-logout">로그아웃</button>
            </form>
        </div>
    </header>

    <main class="dp-body">
        @if (session('status'))
            <div class="dp-flash" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="dp-err"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        @yield('content')
    </main>
</body>
</html>
