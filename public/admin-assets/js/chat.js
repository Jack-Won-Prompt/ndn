/* ==========================================================================
   NDN 채팅 UI — 조직 사용자(NDN·시청·농가·해외협력사) 공용
   window.CHAT_BASE (예: /ndn/chat) 를 기준으로 동작.
   기능: 근로자 검색·대화·전송 + 첨부파일·답장·수정·삭제·읽음표시 (supportworks 이식).
   ========================================================================== */
(function () {
    'use strict';
    var BASE = window.CHAT_BASE;
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var activeId = null;
    var pollTimer = null;
    var replyTarget = null;   // {id, preview}
    var editTarget = null;    // messageId
    var selectedFile = null;

    function jsonApi(url, opts) {
        opts = opts || {};
        opts.headers = Object.assign({ 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, opts.headers || {});
        return fetch(url, opts).then(function (r) { return r.json().catch(function () { return {}; }); });
    }
    function el(tag, cls, txt) { var e = document.createElement(tag); if (cls) e.className = cls; if (txt != null) e.textContent = txt; return e; }
    function fmtSize(n) {
        if (n == null) return '';
        if (n < 1024) return n + ' B';
        if (n < 1048576) return (n / 1024).toFixed(0) + ' KB';
        return (n / 1048576).toFixed(1) + ' MB';
    }

    /* ---------- 대화 목록 ---------- */
    function loadConversations() {
        return jsonApi(BASE + '/conversations').then(function (j) {
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
        cancelReply(); cancelEdit(); clearFile();
        document.getElementById('chat-main').classList.add('is-open');
        document.getElementById('chat-title').textContent = title || '대화';
        loadMessages();
        loadConversations();
        startPoll();
    }

    function loadMessages() {
        if (!activeId) return Promise.resolve();
        return jsonApi(BASE + '/' + activeId + '/messages').then(renderMessages);
    }

    function renderMessages(j) {
        if (j && j.messages == null && j.ok === false) { ndnToast && ndnToast(j.message || '오류', { type: 'error' }); return; }
        var box = document.getElementById('chat-msgs');
        var atBottom = box.scrollHeight - box.scrollTop - box.clientHeight < 40;
        box.innerHTML = '';
        (j.messages || []).forEach(function (m) {
            box.appendChild(buildMessageRow(m));
        });
        if (atBottom) box.scrollTop = box.scrollHeight;
    }

    function buildMessageRow(m) {
        var row = el('div', 'chat-msg' + (m.mine ? ' chat-msg--mine' : ''));
        var bubble = el('div', 'chat-bubble' + (m.deleted ? ' chat-bubble--deleted' : ''));

        // 답장 인용
        if (m.reply) {
            var q = el('div', 'chat-quote');
            q.appendChild(el('span', 'chat-quote__who', m.reply.mine ? '나' : '상대'));
            q.appendChild(el('span', 'chat-quote__text', m.reply.preview || ''));
            bubble.appendChild(q);
        }

        // 첨부
        if (m.file) {
            if (m.file.is_image && m.file.url) {
                var img = el('img', 'chat-attach-img');
                img.src = m.file.url; img.alt = m.file.name; img.loading = 'lazy';
                img.addEventListener('click', function () { window.open(m.file.url, '_blank'); });
                bubble.appendChild(img);
            } else if (m.file.url) {
                var fa = el('a', 'chat-attach-file');
                fa.href = m.file.url; fa.target = '_blank';
                fa.appendChild(el('span', 'chat-attach-file__icon', '📎'));
                var fmeta = el('span', 'chat-attach-file__meta');
                fmeta.appendChild(el('span', 'chat-attach-file__name', m.file.name));
                fmeta.appendChild(el('span', 'chat-attach-file__size', fmtSize(m.file.size)));
                fa.appendChild(fmeta);
                bubble.appendChild(fa);
            }
        }

        // 본문 (상대 메시지가 번역된 경우: 번역본 + 원어 함께 표시)
        if (m.deleted) {
            bubble.appendChild(el('div', 'chat-bubble__body chat-bubble__body--deleted', m.body));
        } else if (m.body) {
            bubble.appendChild(el('div', 'chat-bubble__body', m.body));
            if (m.translated && m.original) {
                var orig = el('div', 'chat-bubble__orig');
                orig.appendChild(el('span', 'chat-bubble__orig-tag', '원어' + (m.original_lang ? '(' + m.original_lang + ')' : '')));
                orig.appendChild(el('span', 'chat-bubble__orig-txt', m.original));
                bubble.appendChild(orig);
            }
        }

        // 메타
        var metaTxt = m.at;
        if (m.translated) metaTxt += ' · 번역됨';
        if (m.edited) metaTxt += ' · 수정됨';
        if (m.mine && m.read) metaTxt += ' · 읽음';
        bubble.appendChild(el('div', 'chat-bubble__meta', metaTxt));

        // 액션 (답장/수정/삭제)
        if (!m.deleted) {
            var acts = el('div', 'chat-acts');
            var replyBtn = el('button', 'chat-act', '답장'); replyBtn.type = 'button';
            replyBtn.addEventListener('click', function () { startReply(m); });
            acts.appendChild(replyBtn);
            if (m.mine) {
                if (m.body && !m.file) {   // 텍스트 메시지만 수정 (첨부 메시지 수정 제외)
                    var editBtn = el('button', 'chat-act', '수정'); editBtn.type = 'button';
                    editBtn.addEventListener('click', function () { startEdit(m); });
                    acts.appendChild(editBtn);
                }
                var delBtn = el('button', 'chat-act chat-act--danger', '삭제'); delBtn.type = 'button';
                delBtn.addEventListener('click', function () { removeMessage(m); });
                acts.appendChild(delBtn);
            }
            bubble.appendChild(acts);
        }

        row.appendChild(bubble);
        return row;
    }

    /* ---------- 답장 / 수정 상태 ---------- */
    function startReply(m) {
        cancelEdit();
        replyTarget = { id: m.id, preview: (m.body || (m.file ? (m.file.is_image ? '📷 사진' : '📎 ' + m.file.name) : '')) };
        showComposeHint('답장', replyTarget.preview, cancelReply);
        document.getElementById('chat-input').focus();
    }
    function cancelReply() { replyTarget = null; hideComposeHintIf('답장'); }

    function startEdit(m) {
        cancelReply(); clearFile();
        editTarget = m.id;
        var input = document.getElementById('chat-input');
        input.value = m.body; input.focus();
        showComposeHint('수정', m.body, cancelEdit);
    }
    function cancelEdit() {
        if (editTarget) { document.getElementById('chat-input').value = ''; }
        editTarget = null; hideComposeHintIf('수정');
    }

    function showComposeHint(kind, text, onCancel) {
        var bar = document.getElementById('chat-compose-hint');
        bar.dataset.kind = kind;
        bar.innerHTML = '';
        bar.appendChild(el('span', 'chat-hint__kind', kind));
        bar.appendChild(el('span', 'chat-hint__text', text || ''));
        var x = el('button', 'chat-hint__x', '×'); x.type = 'button';
        x.addEventListener('click', onCancel);
        bar.appendChild(x);
        bar.style.display = 'flex';
    }
    function hideComposeHintIf(kind) {
        var bar = document.getElementById('chat-compose-hint');
        if (bar && bar.dataset.kind === kind) { bar.style.display = 'none'; bar.dataset.kind = ''; }
    }

    /* ---------- 첨부 파일 선택 ---------- */
    function onFilePicked(file) {
        if (!file) return;
        cancelEdit();   // 수정 중에는 첨부 불가
        selectedFile = file;
        var chip = document.getElementById('chat-file-chip');
        chip.innerHTML = '';
        chip.appendChild(el('span', null, '📎 ' + file.name + ' (' + fmtSize(file.size) + ')'));
        var x = el('button', 'chat-hint__x', '×'); x.type = 'button';
        x.addEventListener('click', clearFile);
        chip.appendChild(x);
        chip.style.display = 'flex';
    }
    function clearFile() {
        selectedFile = null;
        var chip = document.getElementById('chat-file-chip');
        if (chip) { chip.style.display = 'none'; chip.innerHTML = ''; }
        var fi = document.getElementById('chat-file-input');
        if (fi) fi.value = '';
    }

    /* ---------- 전송 ---------- */
    function send() {
        if (!activeId) return;
        var input = document.getElementById('chat-input');
        var body = input.value.trim();

        // 수정 모드
        if (editTarget) {
            if (!body) return;
            var mid = editTarget;
            jsonApi(BASE + '/' + activeId + '/messages/' + mid, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body: body }),
            }).then(function (j) {
                if (j.ok === false) { ndnToast && ndnToast(j.message || '수정 실패', { type: 'error' }); return; }
                input.value = ''; cancelEdit(); renderMessages(j); loadConversations();
            });
            return;
        }

        if (!body && !selectedFile) return;

        var req;
        if (selectedFile) {
            var fd = new FormData();
            if (body) fd.append('body', body);
            fd.append('file', selectedFile);
            if (replyTarget) fd.append('reply_to_id', replyTarget.id);
            req = jsonApi(BASE + '/' + activeId + '/messages', { method: 'POST', body: fd });
        } else {
            req = jsonApi(BASE + '/' + activeId + '/messages', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ body: body, reply_to_id: replyTarget ? replyTarget.id : null }),
            });
        }
        input.value = ''; clearFile(); cancelReply();
        req.then(function (j) {
            if (j.ok === false) { ndnToast && ndnToast(j.message || '전송 실패', { type: 'error' }); return; }
            renderMessages(j); loadConversations();
        }).catch(function () { ndnToast && ndnToast('전송 실패', { type: 'error' }); });
    }

    function removeMessage(m) {
        var go = function () {
            jsonApi(BASE + '/' + activeId + '/messages/' + m.id, { method: 'DELETE' })
                .then(function (j) {
                    if (j.ok === false) { ndnToast && ndnToast(j.message || '삭제 실패', { type: 'error' }); return; }
                    renderMessages(j); loadConversations();
                });
        };
        if (window.ndnConfirm) { ndnConfirm('이 메시지를 삭제할까요?', { okText: '삭제' }).then(function (ok) { if (ok) go(); }); }
        else if (confirm('이 메시지를 삭제할까요?')) { go(); }
    }

    function startPoll() {
        stopPoll();
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
        jsonApi(BASE + '/contacts').then(function (j) {
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
        closeAC();
    }
    function closeNewChat() { document.getElementById('chat-new').classList.remove('is-open'); closeAC(); }

    /* ---------- 근로자 자동완성(typeahead) 검색 ---------- */
    var acItems = [];   // 현재 제안 목록
    var acActive = -1;  // 키보드 활성 인덱스
    var NAT = { VN: '베트남', BD: '방글라데시', LA: '라오스', LK: '스리랑카', KH: '캄보디아', NP: '네팔', KR: '한국' };

    function searchWorkers(q) {
        jsonApi(BASE + '/search-workers?q=' + encodeURIComponent(q)).then(function (j) {
            acItems = j.workers || [];
            acActive = -1;
            renderWorkerAC(q);
        });
    }

    function renderWorkerAC(q) {
        var box = document.getElementById('chat-new-workers');
        box.innerHTML = '';
        if (!acItems.length) {
            box.appendChild(el('div', 'chat-ac__empty', q ? '일치하는 근로자가 없습니다' : '이름을 입력하세요'));
            box.classList.add('is-open');
            return;
        }
        acItems.forEach(function (w, i) {
            var b = el('button', 'chat-ac__item' + (i === acActive ? ' is-active' : '')); b.type = 'button';
            var nm = el('span', 'chat-ac__name'); nm.innerHTML = highlight(w.name, q);
            var sub = el('span', 'chat-ac__sub', natLabel(w.nationality) + ' · ' + (w.locale || '-') + ' · #' + w.id);
            b.appendChild(nm); b.appendChild(sub);
            // mousedown: input blur 로 인한 닫힘보다 먼저 선택 처리
            b.addEventListener('mousedown', function (e) { e.preventDefault(); openWith('worker', w.id); });
            b.addEventListener('mouseenter', function () { acActive = i; markActive(); });
            box.appendChild(b);
        });
        box.classList.add('is-open');
    }

    function markActive() {
        var box = document.getElementById('chat-new-workers');
        [].forEach.call(box.querySelectorAll('.chat-ac__item'), function (c, i) {
            c.classList.toggle('is-active', i === acActive);
        });
        var act = box.querySelectorAll('.chat-ac__item')[acActive];
        if (act) act.scrollIntoView({ block: 'nearest' });
    }
    function moveAC(delta) {
        if (!acItems.length) return;
        acActive = (acActive + delta + acItems.length) % acItems.length;
        markActive();
    }
    function chooseAC() {
        if (acActive >= 0 && acItems[acActive]) openWith('worker', acItems[acActive].id);
        else if (acItems.length === 1) openWith('worker', acItems[0].id);
    }
    function closeAC() {
        var box = document.getElementById('chat-new-workers');
        if (box) { box.innerHTML = ''; box.classList.remove('is-open'); }
        acActive = -1;
    }

    function highlight(text, q) {
        var esc = function (s) { return s.replace(/[&<>"]/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]); }); };
        if (!q) return esc(text);
        var idx = text.toLowerCase().indexOf(q.toLowerCase());
        if (idx < 0) return esc(text);
        return esc(text.slice(0, idx)) + '<mark>' + esc(text.slice(idx, idx + q.length)) + '</mark>' + esc(text.slice(idx + q.length));
    }
    function natLabel(code) { return code ? (NAT[code] || code) + '(' + code + ')' : ''; }

    function openWith(type, id) {
        jsonApi(BASE + '/open', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type: type, id: id }),
        }).then(function (j) {
            closeNewChat();
            openConversation(j.id, null);
            loadConversations();
        });
    }

    /* ---------- 입력바 부가요소 주입 (첨부버튼·답장/수정 힌트·파일칩) ---------- */
    function enhanceComposer() {
        var bar = document.querySelector('.chat-input-bar');
        if (!bar) return;

        // 힌트/칩을 담는 상단 스택
        var stack = el('div', 'chat-compose-stack');
        var hint = el('div', 'chat-hint'); hint.id = 'chat-compose-hint'; hint.style.display = 'none';
        var chip = el('div', 'chat-hint chat-hint--file'); chip.id = 'chat-file-chip'; chip.style.display = 'none';
        stack.appendChild(hint); stack.appendChild(chip);
        bar.parentNode.insertBefore(stack, bar);

        // 첨부 버튼 + 숨김 파일입력
        var fileInput = el('input'); fileInput.type = 'file'; fileInput.id = 'chat-file-input'; fileInput.style.display = 'none';
        fileInput.addEventListener('change', function () { onFilePicked(fileInput.files[0]); });
        var attach = el('button', 'chat-attachbtn', '📎'); attach.type = 'button'; attach.title = '파일 첨부';
        attach.addEventListener('click', function () { fileInput.click(); });
        bar.insertBefore(attach, bar.firstChild);
        bar.appendChild(fileInput);
    }

    /* ---------- 초기화 ---------- */
    function init() {
        enhanceComposer();
        setupRealtime();
        loadConversations();
        document.getElementById('chat-send').addEventListener('click', send);
        document.getElementById('chat-input').addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(); }
            if (e.key === 'Escape') { cancelEdit(); cancelReply(); }
        });
        document.getElementById('chat-new-btn').addEventListener('click', openNewChat);
        document.getElementById('chat-new-close').addEventListener('click', closeNewChat);

        // 근로자 자동완성 검색 (typeahead)
        var sb = document.getElementById('chat-new-search');
        var t;
        sb.addEventListener('input', function () {
            clearTimeout(t);
            var q = sb.value.trim();
            t = setTimeout(function () { searchWorkers(q); }, 200);
        });
        sb.addEventListener('focus', function () { searchWorkers(sb.value.trim()); });
        sb.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); moveAC(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); moveAC(-1); }
            else if (e.key === 'Enter') { e.preventDefault(); chooseAC(); }
            else if (e.key === 'Escape') { closeAC(); sb.blur(); }
        });
        sb.addEventListener('blur', function () { setTimeout(closeAC, 150); });
    }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
