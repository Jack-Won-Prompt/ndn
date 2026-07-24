/* ==========================================================================
   NDN 운영 콘솔 — 공용 UI 헬퍼
   네이티브 alert()/confirm() 를 대체하는 커스텀 토스트·모달.
     window.ndnToast(message, { type, title, duration })
     window.ndnConfirm(message, { title, okText, cancelText, danger }) -> Promise<boolean>
     window.ndnAlert(message, { title, okText, type })               -> Promise<void>
   ========================================================================== */
(function () {
    'use strict';

    /* ---------------- 토스트 ---------------- */
    function toastHost() {
        var el = document.querySelector('.ndn-toasts');
        if (!el) {
            el = document.createElement('div');
            el.className = 'ndn-toasts';
            document.body.appendChild(el);
        }
        return el;
    }

    var ICON = { success: '✓', error: '!', info: 'i' };

    function ndnToast(message, opts) {
        opts = opts || {};
        var type = opts.type || 'info';
        var host = toastHost();

        var toast = document.createElement('div');
        toast.className = 'ndn-toast ndn-toast--' + type;
        toast.setAttribute('role', 'status');

        var icon = document.createElement('span');
        icon.className = 'ndn-toast__icon';
        icon.textContent = ICON[type] || 'i';

        var body = document.createElement('div');
        body.className = 'ndn-toast__body';
        if (opts.title) {
            var t = document.createElement('p');
            t.className = 'ndn-toast__title';
            t.textContent = opts.title;
            body.appendChild(t);
        }
        var m = document.createElement('p');
        m.className = 'ndn-toast__msg';
        m.textContent = message;
        body.appendChild(m);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'ndn-toast__close';
        close.setAttribute('aria-label', '닫기');
        close.innerHTML = '&times;';

        toast.appendChild(icon);
        toast.appendChild(body);
        toast.appendChild(close);
        host.appendChild(toast);

        // 진입 애니메이션
        requestAnimationFrame(function () { toast.classList.add('is-in'); });

        var timer;
        function dismiss() {
            clearTimeout(timer);
            toast.classList.remove('is-in');
            toast.classList.add('is-out');
            toast.addEventListener('transitionend', function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, { once: true });
        }
        close.addEventListener('click', dismiss);
        var dur = opts.duration == null ? 3400 : opts.duration;
        if (dur > 0) timer = setTimeout(dismiss, dur);

        return { dismiss: dismiss };
    }

    /* ---------------- 확인/알림 모달 ---------------- */
    function buildModal(opts) {
        opts = opts || {};
        var overlay = document.createElement('div');
        overlay.className = 'ndn-modal';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        var card = document.createElement('div');
        card.className = 'ndn-modal__card';

        if (opts.title) {
            var title = document.createElement('h2');
            title.className = 'ndn-modal__title';
            title.textContent = opts.title;
            card.appendChild(title);
        }

        var msg = document.createElement('p');
        msg.className = 'ndn-modal__msg';
        msg.textContent = opts.message || '';
        card.appendChild(msg);

        var actions = document.createElement('div');
        actions.className = 'ndn-modal__actions';
        card.appendChild(actions);

        overlay.appendChild(card);
        return { overlay: overlay, actions: actions };
    }

    function openModal(overlay) {
        document.body.appendChild(overlay);
        requestAnimationFrame(function () { overlay.classList.add('is-in'); });
    }

    function closeModal(overlay, cb) {
        overlay.classList.remove('is-in');
        overlay.addEventListener('transitionend', function () {
            if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
            if (cb) cb();
        }, { once: true });
    }

    function ndnConfirm(message, opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var m = buildModal({
                title: opts.title || '확인',
                message: message,
            });

            var cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'ndn-btn ndn-btn--ghost';
            cancel.textContent = opts.cancelText || '취소';

            var ok = document.createElement('button');
            ok.type = 'button';
            ok.className = 'ndn-btn ' + (opts.danger ? 'ndn-btn--danger' : 'ndn-btn--primary');
            ok.textContent = opts.okText || '확인';

            m.actions.appendChild(cancel);
            m.actions.appendChild(ok);

            function done(result) {
                document.removeEventListener('keydown', onKey);
                closeModal(m.overlay, function () { resolve(result); });
            }
            function onKey(e) {
                if (e.key === 'Escape') done(false);
                else if (e.key === 'Enter') done(true);
            }

            cancel.addEventListener('click', function () { done(false); });
            ok.addEventListener('click', function () { done(true); });
            m.overlay.addEventListener('mousedown', function (e) {
                if (e.target === m.overlay) done(false);
            });
            document.addEventListener('keydown', onKey);

            openModal(m.overlay);
            requestAnimationFrame(function () { ok.focus(); });
        });
    }

    function ndnAlert(message, opts) {
        opts = opts || {};
        return new Promise(function (resolve) {
            var m = buildModal({
                title: opts.title || '알림',
                message: message,
            });

            var ok = document.createElement('button');
            ok.type = 'button';
            ok.className = 'ndn-btn ' + (opts.type === 'error' ? 'ndn-btn--danger' : 'ndn-btn--primary');
            ok.textContent = opts.okText || '확인';
            m.actions.appendChild(ok);

            function done() {
                document.removeEventListener('keydown', onKey);
                closeModal(m.overlay, resolve);
            }
            function onKey(e) {
                if (e.key === 'Escape' || e.key === 'Enter') done();
            }
            ok.addEventListener('click', done);
            document.addEventListener('keydown', onKey);

            openModal(m.overlay);
            requestAnimationFrame(function () { ok.focus(); });
        });
    }

    /* ---------------- 읽기 전용 상세 모달 ---------------- */
    // opts: { title, subtitle, note, rows: [[label, value, isPill?], ...], okText }
    // 값에 isPill(true)를 주면 "라벨|kind" 형식을 배지로 렌더한다.
    function ndnDetailModal(opts) {
        opts = opts || {};
        var m = buildModal({ title: opts.title || '상세', message: '' });
        m.overlay.querySelector('.ndn-modal__card').classList.add('ndn-modal__card--detail');

        var msgEl = m.overlay.querySelector('.ndn-modal__msg');
        if (msgEl) msgEl.remove();

        if (opts.subtitle) {
            var sub = document.createElement('p');
            sub.className = 'ndn-modal__sub';
            sub.textContent = opts.subtitle;
            m.overlay.querySelector('.ndn-modal__title').insertAdjacentElement('afterend', sub);
        }

        var dl = document.createElement('dl');
        dl.className = 'ndn-detail';
        (opts.rows || []).forEach(function (r) {
            var dt = document.createElement('dt');
            dt.textContent = r[0];
            var dd = document.createElement('dd');
            var val = r[1];
            if (r[2] && val != null && String(val).indexOf('|') > -1) {
                var parts = String(val).split('|');
                var pill = document.createElement('span');
                pill.className = 'ndn-dpill' + (parts[1] ? ' ndn-dpill--' + parts[1] : '');
                pill.textContent = parts[0];
                dd.appendChild(pill);
            } else {
                dd.textContent = (val == null || val === '') ? '—' : String(val);
            }
            dl.appendChild(dt);
            dl.appendChild(dd);
        });
        m.actions.parentNode.insertBefore(dl, m.actions);

        if (opts.note) {
            var note = document.createElement('p');
            note.className = 'ndn-modal__note';
            note.textContent = opts.note;
            m.actions.parentNode.insertBefore(note, m.actions);
        }

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'ndn-btn ndn-btn--primary';
        close.textContent = opts.okText || '닫기';
        m.actions.appendChild(close);

        function done() {
            document.removeEventListener('keydown', onKey);
            closeModal(m.overlay);
        }
        function onKey(e) {
            if (e.key === 'Escape' || e.key === 'Enter') done();
        }
        close.addEventListener('click', done);
        m.overlay.addEventListener('mousedown', function (e) {
            if (e.target === m.overlay) done();
        });
        document.addEventListener('keydown', onKey);

        openModal(m.overlay);
        requestAnimationFrame(function () { close.focus(); });
        return { close: done };
    }

    window.ndnToast = ndnToast;
    window.ndnConfirm = ndnConfirm;
    window.ndnAlert = ndnAlert;
    window.ndnDetailModal = ndnDetailModal;
})();
