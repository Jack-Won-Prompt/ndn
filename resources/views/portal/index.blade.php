<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>협력 포털 — N.D.N Korea</title>
@include('partials.tz-cookie')
<link rel="icon" type="image/svg+xml" href="{{ asset('site/assets/favicon.svg') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('site/assets/favicon-32.png') }}">
<style>
    @font-face{font-family:"Pretendard Variable";src:url("{{ asset('site/assets/fonts/PretendardVariable.woff2') }}") format("woff2-variations");font-weight:45 920;font-display:swap;}
    body{margin:0;font-family:"Pretendard Variable","Pretendard",-apple-system,"Malgun Gothic",sans-serif;background:#fff;color:#1B1E24;-webkit-font-smoothing:antialiased;}
    .portal-top{height:56px;display:flex;align-items:center;justify-content:space-between;padding:0 22px;border-bottom:1px solid #E3E6EA;}
    .portal-brand b{font-size:18px;font-weight:800;letter-spacing:.04em;}
    .portal-brand span{font-size:12px;color:#6B7280;margin-left:8px;letter-spacing:.1em;text-transform:uppercase;}
    .portal-user{display:flex;align-items:center;gap:12px;font-size:13px;color:#333A44;}
    .portal-user form{margin:0;}
    .portal-logout{font-family:inherit;font-size:13px;color:#6B7280;background:none;border:1px solid #E3E6EA;border-radius:6px;padding:6px 12px;cursor:pointer;}
    .portal-logout:hover{color:#1B1E24;border-color:#9AA1AC;}
    .portal-nav{display:flex;align-items:center;gap:6px;margin-left:26px;}
    .portal-nav a{font-size:14px;color:#4B5563;text-decoration:none;padding:7px 14px;border-radius:8px;font-weight:600;}
    .portal-nav a:hover{background:#EEF1F4;color:#1B1E24;}
    .portal-nav a.is-active{background:#1E9C92;color:#fff;}
    .portal-left{display:flex;align-items:center;}
    .portal-body{padding:22px;}
</style>
<link rel="stylesheet" href="{{ asset('admin-assets/css/embed.css') }}?v={{ @filemtime(public_path('admin-assets/css/embed.css')) }}">
<link rel="stylesheet" href="{{ asset('admin-assets/css/ui.css') }}?v={{ @filemtime(public_path('admin-assets/css/ui.css')) }}">
<link rel="stylesheet" href="{{ asset('admin-assets/css/chat.css') }}?v={{ @filemtime(public_path('admin-assets/css/chat.css')) }}">
</head>
<body>
    <header class="portal-top">
        <div class="portal-left">
            <div class="portal-brand"><b>N.D.N</b><span>협력 포털</span></div>
            <nav class="portal-nav">
                @if ($user->isRole(\App\Shared\Enums\UserRole::FarmOwner))
                    <a href="{{ route('demand.index') }}">수요 신청</a>
                @endif
                <a href="{{ route('portal.index') }}" class="is-active">채팅</a>
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
        <div class="chat-wrap" style="height:calc(100vh - 100px)">
            <div class="chat-list-pane">
                <div class="chat-list-head">
                    <b>대화</b>
                    <button type="button" id="chat-new-btn" class="chat-newbtn">+ 새 대화</button>
                </div>
                <div class="chat-list" id="chat-list"></div>
            </div>
            <div class="chat-main-pane" id="chat-main">
                <div class="chat-main-head" id="chat-title">대화를 선택하세요</div>
                <div class="chat-msgs" id="chat-msgs"></div>
                <div class="chat-input-bar">
                    <textarea id="chat-input" placeholder="메시지를 입력하세요 (Enter 전송)"></textarea>
                    <button type="button" id="chat-send" class="chat-sendbtn">전송</button>
                </div>
                <div class="chat-new" id="chat-new">
                    <div class="chat-new__card">
                        <div class="chat-new__head">
                            <b>새 대화</b>
                            <button type="button" id="chat-new-close" class="chat-new__close">&times;</button>
                        </div>
                        <div class="chat-new__body">
                            <div id="chat-new-worker-wrap">
                                <div class="chat-new__label">근로자 검색</div>
                                <input type="search" id="chat-new-search" placeholder="이름으로 검색 (자동완성)" autocomplete="off">
                                <div id="chat-new-workers"></div>
                            </div>
                            <div class="chat-new__label">조직</div>
                            <div id="chat-new-orgs"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        window.CHAT_BASE = '{{ url('chat') }}';
        window.CHAT_ME = { type: '{{ $me[0] }}', id: {{ $me[1] ?? 0 }} };
        window.PUSHER_KEY = '{{ config('broadcasting.connections.pusher.key') }}';
        window.PUSHER_CLUSTER = '{{ config('broadcasting.connections.pusher.options.cluster') }}';
        window.CHAT_AUTH = '{{ url('broadcasting/auth') }}';
    </script>
    <script src="{{ asset('admin-assets/js/ui.js') }}?v={{ @filemtime(public_path('admin-assets/js/ui.js')) }}"></script>
    <script src="{{ asset('admin-assets/vendor/pusher/pusher.min.js') }}"></script>
    <script src="{{ asset('admin-assets/js/chat.js') }}?v={{ @filemtime(public_path('admin-assets/js/chat.js')) }}"></script>
</body>
</html>
