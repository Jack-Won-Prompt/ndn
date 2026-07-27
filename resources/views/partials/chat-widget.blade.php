{{-- 회사소개 사이트 우하단 "문의하기" 실시간 채팅 위젯 (익명 방문자 ↔ NDN 관리자) --}}
{{-- 정적 라벨은 텍스트 노드로 두어 SiteTranslator 가 방문자 언어로 자동번역한다. --}}
<div class="cw" id="cw" data-msg-url="{{ route('site.chat.message') }}" data-poll-url="{{ route('site.chat.poll') }}">
    {{-- 런처 버튼 --}}
    <button type="button" class="cw-launch" id="cw-launch" aria-expanded="false" aria-controls="cw-panel">
        <span class="cw-launch__icon" aria-hidden="true">💬</span>
        <span class="cw-launch__label">문의하기</span>
    </button>

    {{-- 채팅 패널 --}}
    <section class="cw-panel" id="cw-panel" hidden aria-label="실시간 문의">
        <header class="cw-head">
            <div class="cw-head__t">
                <b>실시간 문의</b>
                <span class="cw-head__sub">보통 몇 분 내 답변드립니다</span>
            </div>
            <button type="button" class="cw-close" id="cw-close" aria-label="닫기">&times;</button>
        </header>

        <div class="cw-msgs" id="cw-msgs">
            <div class="cw-bubble cw-bubble--in cw-greet">
                안녕하세요! 궁금하신 점을 남겨주시면 담당자가 확인 후 답변드립니다.
            </div>
        </div>

        <form class="cw-inbar" id="cw-form">
            <textarea class="cw-input" id="cw-input" rows="1" maxlength="2000" aria-label="메시지"></textarea>
            <button type="submit" class="cw-send" id="cw-send">보내기</button>
        </form>
    </section>

    {{-- JS 에서 읽는 번역 문자열(속성은 번역 대상이 아니므로 텍스트 노드로 보관) --}}
    <span class="cw-i18n" id="cw-i18n-ph" hidden>메시지를 입력하세요…</span>
    <span class="cw-i18n" id="cw-i18n-err" hidden>전송에 실패했습니다. 잠시 후 다시 시도해 주세요.</span>
</div>

<style>
    .cw { position: fixed; right: 20px; bottom: 20px; z-index: 9999; font-family: inherit; }
    .cw-launch { display: inline-flex; align-items: center; gap: 8px; border: 0; cursor: pointer;
        background: #1E9C92; color: #fff; font-family: inherit; font-size: 15px; font-weight: 700;
        padding: 12px 18px; border-radius: 999px; box-shadow: 0 8px 24px rgba(15,23,42,.22); transition: transform .15s, box-shadow .15s; }
    .cw-launch:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(30,156,146,.35); }
    .cw-launch__icon { font-size: 18px; line-height: 1; }
    .cw.is-open .cw-launch { display: none; }

    .cw-panel[hidden] { display: none; }
    .cw-panel { position: absolute; right: 0; bottom: 0; width: 360px; max-width: calc(100vw - 32px);
        height: 520px; max-height: calc(100vh - 40px); background: #fff; border-radius: 16px;
        box-shadow: 0 20px 60px rgba(15,23,42,.28); display: flex; flex-direction: column; overflow: hidden;
        animation: cw-pop .18s ease; }
    @keyframes cw-pop { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: none; } }

    .cw-head { display: flex; align-items: center; justify-content: space-between; gap: 8px;
        padding: 14px 16px; background: #1E9C92; color: #fff; }
    .cw-head__t { display: flex; flex-direction: column; line-height: 1.35; }
    .cw-head__t b { font-size: 15px; }
    .cw-head__sub { font-size: 12px; opacity: .85; }
    .cw-close { border: 0; background: transparent; color: #fff; font-size: 24px; line-height: 1; cursor: pointer; padding: 0 4px; opacity: .9; }
    .cw-close:hover { opacity: 1; }

    .cw-msgs { flex: 1; overflow-y: auto; padding: 14px; display: flex; flex-direction: column; gap: 8px;
        background: #F6F8F8; scroll-behavior: smooth; }
    .cw-bubble { max-width: 78%; padding: 9px 12px; border-radius: 14px; font-size: 14px; line-height: 1.5;
        word-break: break-word; white-space: pre-wrap; box-shadow: 0 1px 2px rgba(15,23,42,.06); }
    .cw-bubble--in { align-self: flex-start; background: #fff; color: #0F172A; border-bottom-left-radius: 4px; }
    .cw-bubble--out { align-self: flex-end; background: #1E9C92; color: #fff; border-bottom-right-radius: 4px; }
    .cw-bubble__at { display: block; margin-top: 3px; font-size: 10px; opacity: .6; }
    .cw-greet { background: #E7F3F1; color: #0F172A; }

    .cw-inbar { display: flex; align-items: flex-end; gap: 8px; padding: 10px; border-top: 1px solid #E5EBEA; background: #fff; }
    .cw-input { flex: 1; resize: none; border: 1px solid #D4DCDB; border-radius: 12px; padding: 9px 12px;
        font-family: inherit; font-size: 14px; line-height: 1.4; max-height: 120px; outline: none; }
    .cw-input:focus { border-color: #1E9C92; box-shadow: 0 0 0 3px rgba(30,156,146,.15); }
    .cw-send { border: 0; cursor: pointer; background: #1E9C92; color: #fff; font-family: inherit; font-weight: 700;
        font-size: 14px; padding: 9px 14px; border-radius: 12px; flex: 0 0 auto; }
    .cw-send:disabled { opacity: .5; cursor: default; }

    @media (max-width: 480px) {
        .cw { right: 12px; bottom: 12px; }
        .cw-panel { width: calc(100vw - 24px); height: calc(100vh - 24px); }
    }
</style>

<script>
(function () {
    var root = document.getElementById('cw');
    if (!root) return;
    var launch = document.getElementById('cw-launch');
    var panel = document.getElementById('cw-panel');
    var closeBtn = document.getElementById('cw-close');
    var msgs = document.getElementById('cw-msgs');
    var form = document.getElementById('cw-form');
    var input = document.getElementById('cw-input');
    var sendBtn = document.getElementById('cw-send');
    var MSG_URL = root.getAttribute('data-msg-url');
    var POLL_URL = root.getAttribute('data-poll-url');
    var csrf = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var i18n = {
        ph: (document.getElementById('cw-i18n-ph') || {}).textContent || '메시지를 입력하세요…',
        err: (document.getElementById('cw-i18n-err') || {}).textContent || '전송에 실패했습니다.',
    };
    input.setAttribute('placeholder', i18n.ph.trim());

    var pollTimer = null, lastIds = '', started = false;

    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; }

    function render(list) {
        // 서버가 보낸 전체 메시지로 교체(중복 렌더 방지 위해 시그니처 비교)
        var sig = list.map(function (m) { return m.id + ':' + (m.body || '').length; }).join(',');
        if (sig === lastIds) return;
        lastIds = sig;
        // 인사말은 보존하고 그 뒤 메시지만 다시 그림
        Array.prototype.slice.call(msgs.querySelectorAll('.cw-bubble:not(.cw-greet)')).forEach(function (n) { n.remove(); });
        list.forEach(function (m) {
            var b = document.createElement('div');
            b.className = 'cw-bubble ' + (m.mine ? 'cw-bubble--out' : 'cw-bubble--in');
            b.innerHTML = esc(m.body) + (m.at ? '<span class="cw-bubble__at">' + esc(m.at) + '</span>' : '');
            msgs.appendChild(b);
        });
        msgs.scrollTop = msgs.scrollHeight;
    }

    function poll() {
        fetch(POLL_URL, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (j) { if (j && j.messages) render(j.messages); })
            .catch(function () {});
    }

    function startPolling() {
        if (pollTimer) return;
        poll();
        pollTimer = setInterval(poll, 4000);
    }
    function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

    function open() {
        root.classList.add('is-open');
        panel.hidden = false;
        launch.setAttribute('aria-expanded', 'true');
        input.focus();
        startPolling();
    }
    function close() {
        root.classList.remove('is-open');
        panel.hidden = true;
        launch.setAttribute('aria-expanded', 'false');
        stopPolling();
    }

    launch.addEventListener('click', open);
    closeBtn.addEventListener('click', close);

    // textarea 자동 높이
    input.addEventListener('input', function () {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 120) + 'px';
    });
    // Enter 전송 / Shift+Enter 줄바꿈
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var body = input.value.trim();
        if (!body) return;
        sendBtn.disabled = true;
        fetch(MSG_URL, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            credentials: 'same-origin',
            body: JSON.stringify({ body: body }),
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                if (res.ok && res.j.messages) {
                    input.value = ''; input.style.height = 'auto';
                    render(res.j.messages);
                    started = true;
                    startPolling();
                } else {
                    alert(res.j && res.j.message ? res.j.message : i18n.err.trim());
                }
            })
            .catch(function () { alert(i18n.err.trim()); })
            .finally(function () { sendBtn.disabled = false; input.focus(); });
    });
})();
</script>
