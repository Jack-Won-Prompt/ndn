@extends('portal.layout', ['active' => 'chat'])
@section('title', '채팅')

@push('head')
    {{-- 채팅 마크업은 chat.js 가 만든다. 그래서 chat.css 를 그대로 쓰고,
         portal-chat.css 가 토큰만 새 디자인으로 갈아끼운다. 콘솔은 영향 없다. --}}
    <link rel="stylesheet" href="{{ asset('admin-assets/css/embed.css') }}?v={{ @filemtime(public_path('admin-assets/css/embed.css')) }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/ui.css') }}?v={{ @filemtime(public_path('admin-assets/css/ui.css')) }}">
    <link rel="stylesheet" href="{{ asset('admin-assets/css/chat.css') }}?v={{ @filemtime(public_path('admin-assets/css/chat.css')) }}">
    <link rel="stylesheet" href="{{ asset('site/assets/css/portal-chat.css') }}?v={{ @filemtime(public_path('site/assets/css/portal-chat.css')) }}">
@endpush

@section('body')
    <div class="chat-wrap">
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

    @push('scripts')
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
    @endpush
@endsection
