/* ==========================================================================
   NDN 운영 콘솔 — MDI 탭 워크스페이스
   메뉴 클릭 시 화면 전환 없이 iframe 탭을 생성/활성화한다.
   ========================================================================== */
(function () {
    'use strict';

    var BASE = window.NDN_ADMIN.base;          // 예: /ndn
    var SCREEN_URL = window.NDN_ADMIN.screenUrl; // 예: /ndn/admin/screen
    var STORE_KEY = 'ndn.admin.tabs';

    var tabbar = document.getElementById('tabbar');
    var panes  = document.getElementById('tabpanes');
    var open = {};      // key -> { title, url }
    var order = [];     // 탭 순서
    var active = null;

    function persist() {
        try {
            localStorage.setItem(STORE_KEY, JSON.stringify({ order: order, active: active }));
        } catch (e) { /* noop */ }
    }

    function restore() {
        try {
            var raw = JSON.parse(localStorage.getItem(STORE_KEY) || '{}');
            return raw && raw.order ? raw : null;
        } catch (e) { return null; }
    }

    function screenSrc(key) {
        // key 가 'workers/12' 처럼 하위 경로를 포함할 수 있다
        return SCREEN_URL + '/' + key;
    }

    function render() {
        // 탭바
        tabbar.innerHTML = '';
        order.forEach(function (key) {
            var meta = open[key];
            var tab = document.createElement('div');
            tab.className = 'tab' + (key === active ? ' is-active' : '') + (key === 'dashboard' ? ' tab--home' : '');
            tab.setAttribute('data-key', key);

            var label = document.createElement('span');
            label.className = 'tab__label';
            label.textContent = meta.title;
            tab.appendChild(label);

            var close = document.createElement('span');
            close.className = 'tab__close';
            close.textContent = '×';
            close.setAttribute('data-close', key);
            tab.appendChild(close);

            tabbar.appendChild(tab);
        });

        // iframe pane 들: 없는 것만 생성 (기존 iframe 은 재로딩하지 않음)
        order.forEach(function (key) {
            var id = 'pane-' + cssId(key);
            var pane = document.getElementById(id);
            if (!pane) {
                pane = document.createElement('iframe');
                pane.className = 'tabpane';
                pane.id = id;
                pane.src = screenSrc(key);
                panes.appendChild(pane);
            }
            pane.classList.toggle('is-active', key === active);
        });

        // 닫힌 탭의 pane 제거
        Array.prototype.slice.call(panes.querySelectorAll('.tabpane')).forEach(function (pane) {
            if (paneKeyOf(pane) === null) pane.remove();
        });

        // 사이드바 활성 표시 (최상위 화면 키 기준)
        var topKey = (active || '').split('/')[0];
        Array.prototype.slice.call(document.querySelectorAll('.nav-item')).forEach(function (n) {
            n.classList.toggle('is-active', n.getAttribute('data-screen') === topKey);
        });

        persist();
    }

    function cssId(key) { return key.replace(/[^a-z0-9]/gi, '_'); }

    // pane id ↔ key 매핑: 현재 열린 key 목록으로 직접 대조
    function paneKeyOf(pane) {
        for (var i = 0; i < order.length; i++) {
            if ('pane-' + cssId(order[i]) === pane.id) return order[i];
        }
        return null;
    }

    function openTab(key, title) {
        if (!open[key]) {
            open[key] = { title: title };
            order.push(key);
        } else if (title) {
            open[key].title = title;
        }
        active = key;
        render();
    }

    function activate(key) {
        if (open[key]) { active = key; render(); }
    }

    function closeTab(key) {
        if (!open[key]) return;
        delete open[key];
        var idx = order.indexOf(key);
        order.splice(idx, 1);
        if (active === key) {
            active = order[Math.max(0, idx - 1)] || order[0] || null;
        }
        render();
    }

    // 사이드바 메뉴 클릭 → 탭 열기
    document.querySelectorAll('.nav-item').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openTab(btn.getAttribute('data-screen'), btn.getAttribute('data-title'));
        });
    });

    // 탭바 클릭 (활성/닫기)
    tabbar.addEventListener('click', function (e) {
        var closeKey = e.target.getAttribute && e.target.getAttribute('data-close');
        if (closeKey) { e.stopPropagation(); closeTab(closeKey); return; }
        var tab = e.target.closest ? e.target.closest('.tab') : null;
        if (tab) activate(tab.getAttribute('data-key'));
    });

    // iframe 안에서 다른 화면을 탭으로 열도록 요청하는 메시지 처리
    // (예: 근로자 목록에서 상세 열기)
    window.addEventListener('message', function (e) {
        var d = e.data || {};
        if (d.ndnOpenTab && d.key) { openTab(d.key, d.title || d.key); }
    });

    // 초기 복원 (없으면 대시보드)
    var titles = window.NDN_ADMIN.titles || {};
    var saved = restore();
    if (saved && saved.order.length) {
        saved.order.forEach(function (key) {
            var topKey = key.split('/')[0];
            open[key] = { title: titles[key] || titles[topKey] || key };
            order.push(key);
        });
        active = saved.active && open[saved.active] ? saved.active : order[0];
        render();
    } else {
        openTab('dashboard', titles.dashboard || '대시보드');
    }
})();
