/* ==========================================================================
   NDN 채팅 UI — 조직 사용자(NDN·시청·농가·해외협력사) 공용
   window.CHAT_BASE (예: /ndn/chat) 를 기준으로 동작. 근로자 검색·대화·전송.
   ========================================================================== */
(function () {
    'use strict';
    var BASE = window.CHAT_BASE;
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var activeId = null;
    var pollTimer = null;

    function api(url, opts) {
        opts = opts || {};
        opts.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, opts.headers || {});
        return fetch(url, opts).then(function (r) { return r.json(); });
    }
    function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; }

    /* ---------- 대화 목록 ---------- */
    function loadConversations() {
        return api(BASE + '/conversations').then(function (j) {
            var list = document.getElementById('chat-list');
            list.innerHTML = '';
            (j.conversations || []).forEach(function (c) {
                var item = el('button', 'chat-conv' + (c.id === activeId ? ' is-active' : ''));
                item.type = 'button';
                var top = el('div', 'chat-conv__top');
                top.appendChild(el('span', 'chat-conv__title', c.title));
                if (c.unread > 0) top.appendChild(el('span', 'chat-conv__badge', String(c.unread)));
                item.appendChild(top);
                item.appendChild(el('div', 'chat-conv__last', c.last || '대화를 시작하세요'));
                item.addEventListener('click', function () { openConversation(c.id, c.title); });
                list.appendChild(item);
            });
            if (!(j.conversations || []).length) {
                list.appendChild(el('div', 'chat-empty', '대화가 없습니다. [새 대화]로 시작하세요.'));
            }
        });
    }

    /* ---------- 대화 열기 + 메시지 ---------- */
    function openConversation(id, title) {
        activeId = id;
        document.getElementById('chat-main').classList.add('is-open');
        document.getElementById('chat-title').textContent = title || '대화';
        loadMessages();
        loadConversations();
        startPoll();
    }

    function loadMessages() {
        if (!activeId) return Promise.resolve();
        return api(BASE + '/' + activeId + '/messages').then(renderMessages);
    }

    function renderMessages(j) {
        var box = document.getElementById('chat-msgs');
        var atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 40;
        box.innerHTML = '';
        (j.messages || []).forEach(function (m) {
            var row = el('div', 'chat-msg' + (m.mine ? ' chat-msg--mine' : ''));
            var bubble = el('div', 'chat-bubble');
            bubble.appendChild(el('div', 'chat-bubble__body', m.body));
            var meta = el('div', 'chat-bubble__meta', m.at + (m.translated ? ' · 번역됨' : ''));
            bubble.appendChild(meta);
            row.appendChild(bubble);
            box.appendChild(row);
        });
        if (atBottom) box.scrollTop = box.scrollHeight;
    }

    function send() {
        var input = document.getElementById('chat-input');
        var body = input.value.trim();
        if (!body || !activeId) return;
        input.value = '';
        api(BASE + '/' + activeId + '/messages', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ body: body }),
        }).then(function (j) { renderMessages(j); loadConversations(); })
          .catch(function () { ndnToast && ndnToast('전송 실패', { type: 'error' }); });
    }

    function startPoll() {
        stopPoll();
        // Pusher 실시간이 있으면 폴링은 느슨한 안전망(15초), 없으면 4초.
        var interval = window.__chatRealtime ? 15000 : 4000;
        pollTimer = setInterval(function () { loadMessages(); loadConversations(); }, interval);
    }
    function stopPoll() { if (pollTimer) clearInterval(pollTimer); }

    /* ---------- 실시간 (Pusher) ---------- */
    function setupRealtime() {
        if (!window.PUSHER_KEY || typeof Pusher === 'undefined' || !window.CHAT_ME) return;
        try {
            var pusher = new Pusher(window.PUSHER_KEY, {
                cluster: window.PUSHER_CLUSTER,
                forceTLS: true,
                authEndpoint: window.CHAT_AUTH,
                auth: { headers: { 'X-CSRF-TOKEN': token } },
            });
            var name = 'private-chat.party.' + window.CHAT_ME.type + '.' + (window.CHAT_ME.id || 0);
            var ch = pusher.subscribe(name);
            ch.bind('message.new', function (data) {
                loadConversations();
                if (data && data.conversation_id === activeId) loadMessages();
            });
            window.__chatRealtime = true;
        } catch (e) { /* 폴링 폴백 */ }
    }

    /* ---------- 새 대화 (근로자 검색 + 조직 연락처) ---------- */
    function openNewChat() {
        var panel = document.getElementById('chat-new');
        panel.classList.add('is-open');
        api(BASE + '/contacts').then(function (j) {
            var orgBox = document.getElementById('chat-new-orgs');
            orgBox.innerHTML = '';
            (j.orgs || []).forEach(function (o) {
                var b = el('button', 'chat-contact'); b.type = 'button';
                b.appendChild(el('span', 'chat-contact__name', o.name));
                b.appendChild(el('span', 'chat-contact__label', o.label));
                b.addEventListener('click', function () { openWith(o.type, o.id); });
                orgBox.appendChild(b);
            });
            document.getElementById('chat-new-worker-wrap').style.display = j.canSearchWorker ? '' : 'none';
        });
        searchWorkers('');
    }
    function closeNewChat() { document.getElementById('chat-new').classList.remove('is-open'); }

    function searchWorkers(q) {
        api(BASE + '/search-workers?q=' + encodeURIComponent(q)).then(function (j) {
            var box = document.getElementById('chat-new-workers');
            box.innerHTML = '';
            (j.workers || []).forEach(function (w) {
                var b = el('button', 'chat-contact'); b.type = 'button';
                b.appendChild(el('span', 'chat-contact__name', w.name));
                b.appendChild(el('span', 'chat-contact__label', w.nationality + ' · 근로자'));
                b.addEventListener('click', function () { openWith('worker', w.id); });
                box.appendChild(b);
            });
        });
    }

    function openWith(type, id) {
        api(BASE + '/open', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, id: id }),
        }).then(function (j) {
            closeNewChat();
            openConversation(j.id, null);
            loadConversations();
        });
    }

    /* ---------- 초기화 ---------- */
    function init() {
        setupRealtime();
        loadConversations();
        document.getElementById('chat-send').addEventListener('click', send);
        document.getElementById('chat-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
        });
        document.getElementById('chat-new-btn').addEventListener('click', openNewChat);
        document.getElementById('chat-new-close').addEventListener('click', closeNewChat);
        var sb = document.getElementById('chat-new-search');
        var t; sb.addEventListener('input', function () { clearTimeout(t); t = setTimeout(function () { searchWorkers(sb.value.trim()); }, 250); });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
