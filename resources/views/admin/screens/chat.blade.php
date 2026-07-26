@extends('admin.screens.layout')
@section('title', '채팅')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">채팅</h1>
            <p class="screen__sub">시청 · 농가 · 근로자와 대화 · <strong>자동 번역</strong>(근로자는 자국어, 관리자는 한국어)</p>
        </div>
    </div>

    <div class="chat-wrap" style="height:calc(100vh - 172px)">
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
                <textarea id="chat-input" placeholder="메시지를 입력하세요 (Enter 전송, Shift+Enter 줄바꿈)"></textarea>
                <button type="button" id="chat-send" class="chat-sendbtn">전송</button>
            </div>

            <div class="chat-new" id="chat-new">
                <div class="chat-new__card">
                    <div class="chat-new__head">
                        <b>새 대화</b>
                        <button type="button" id="chat-new-close" class="chat-new__close">&times;</button>
                    </div>
                    <div class="chat-new__body">
                        <div class="chat-new__label">조직</div>
                        <div id="chat-new-orgs"></div>
                        <div id="chat-new-worker-wrap">
                            <div class="chat-new__label">근로자 검색</div>
                            <input type="search" id="chat-new-search" placeholder="이름으로 검색">
                            <div id="chat-new-workers"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
<link rel="stylesheet" href="{{ asset('admin-assets/css/chat.css') }}?v={{ @filemtime(public_path('admin-assets/css/chat.css')) }}">
<script>window.CHAT_BASE = '{{ url('chat') }}';</script>
<script src="{{ asset('admin-assets/js/chat.js') }}?v={{ @filemtime(public_path('admin-assets/js/chat.js')) }}"></script>
@endsection
