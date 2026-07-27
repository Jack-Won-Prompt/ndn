@extends('admin.screens.layout')
@section('title', '문의하기')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">문의하기</h1>
            <p class="screen__sub">홈페이지 방문자 문의 · <strong>자동 번역</strong>(원어 병기) · 채팅과 분리된 별도 창구</p>
        </div>
    </div>

    <div class="chat-wrap" style="height:calc(100vh - 172px)">
        <div class="chat-list-pane">
            <div class="chat-list-head"><b>문의 목록</b></div>
            <div class="chat-list" id="iq-list"></div>
        </div>

        <div class="chat-main-pane" id="iq-main">
            <div class="chat-main-head" id="iq-title">문의를 선택하세요</div>
            <div class="chat-msgs" id="iq-msgs"></div>
            <div class="chat-input-bar" id="iq-inputbar" hidden>
                <textarea id="iq-input" placeholder="답변을 입력하세요 (Enter 전송, Shift+Enter 줄바꿈)"></textarea>
                <button type="button" id="iq-send" class="chat-sendbtn">전송</button>
            </div>
        </div>
    </div>
@endsection

@section('script')
<link rel="stylesheet" href="{{ asset('admin-assets/css/chat.css') }}?v={{ @filemtime(public_path('admin-assets/css/chat.css')) }}">
<script>
(function () {
    var BASE = '{{ url('admin/inquiries') }}';
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var listEl = document.getElementById('iq-list');
    var msgsEl = document.getElementById('iq-msgs');
    var titleEl = document.getElementById('iq-title');
    var inputBar = document.getElementById('iq-inputbar');
    var input = document.getElementById('iq-input');
    var sendBtn = document.getElementById('iq-send');
    var current = null, pollTimer = null;

    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

    function loadList() {
        fetch(BASE + '/conversations', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var convs = j.conversations || [];
                listEl.innerHTML = '';
                var totalUnread = 0;
                if (!convs.length) { listEl.innerHTML = '<div class="chat-empty">문의가 없습니다.</div>'; }
                convs.forEach(function (c) {
                    totalUnread += (c.unread || 0);
                    var item = document.createElement('button');
                    item.type = 'button';
                    item.className = 'chat-conv' + (current === c.id ? ' is-active' : '');
                    item.innerHTML =
                        '<div class="chat-conv__top"><span class="chat-conv__title">' + esc(c.title) + '</span>'
                        + (c.unread ? '<span class="chat-conv__badge">' + c.unread + '</span>' : '') + '</div>'
                        + '<div class="chat-conv__last">' + esc(c.last || '') + (c.last_at ? ' · ' + esc(c.last_at) : '') + '</div>';
                    item.addEventListener('click', function () { open(c.id, c.title); });
                    listEl.appendChild(item);
                });
                // 사이드바 '문의하기' 배지를 현재 미읽음 수로 동기화
                if (window.parent) window.parent.postMessage({ ndnBadge: { key: 'inquiries', count: totalUnread } }, '*');
            });
    }

    function renderMessages(list) {
        msgsEl.innerHTML = '';
        list.forEach(function (m) {
            var wrap = document.createElement('div');
            wrap.className = 'chat-msg' + (m.mine ? ' chat-msg--mine' : '');
            var bubble = document.createElement('div');
            bubble.className = 'chat-bubble';
            var body = '<div class="chat-bubble__body' + (m.deleted ? ' chat-bubble__body--deleted' : '') + '">' + esc(m.body) + '</div>';
            if (m.translated && m.original) {
                body += '<div class="chat-bubble__orig"><span class="chat-bubble__orig-tag">원어' + (m.original_lang ? '(' + esc(m.original_lang) + ')' : '') + '</span>'
                    + '<span class="chat-bubble__orig-txt">' + esc(m.original) + '</span></div>';
            }
            var meta = m.at + (m.translated ? ' · 번역됨' : '') + (m.edited ? ' · 수정됨' : '');
            body += '<div class="chat-bubble__meta">' + esc(meta) + '</div>';
            bubble.innerHTML = body;
            wrap.appendChild(bubble);
            msgsEl.appendChild(wrap);
        });
        msgsEl.scrollTop = msgsEl.scrollHeight;
    }

    function loadMessages() {
        if (!current) return;
        fetch(BASE + '/' + current + '/messages', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (j) { renderMessages(j.messages || []); });
    }

    function open(id, title) {
        current = id;
        titleEl.textContent = title;
        inputBar.hidden = false;
        Array.prototype.forEach.call(listEl.children, function (n) { n.classList.remove('is-active'); });
        loadMessages();
        loadList();   // 읽음 처리로 미읽음 배지 갱신
    }

    function send() {
        var body = input.value.trim();
        if (!body || !current) return;
        sendBtn.disabled = true;
        fetch(BASE + '/' + current + '/messages', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ body: body }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok) { input.value = ''; renderMessages(res.j.messages || []); }
                else if (window.ndnToast) ndnToast(res.j.message || '전송 실패', { type: 'error' });
            })
            .finally(function () { sendBtn.disabled = false; input.focus(); });
    }

    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
    });

    loadList();
    pollTimer = setInterval(function () { loadList(); if (current) loadMessages(); }, 5000);
})();
</script>
@endsection
