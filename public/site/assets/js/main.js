/* N.D.N Korea — 정적 사이트 공통 스크립트 */
(function () {
    'use strict';

    /* ---- 모바일 내비게이션 ---- */
    var toggle = document.querySelector('.nav-toggle');
    var nav    = document.getElementById('primary-nav');

    if (toggle && nav) {
        toggle.addEventListener('click', function () {
            var open = nav.getAttribute('data-open') === 'true';
            nav.setAttribute('data-open', String(!open));
            toggle.setAttribute('aria-expanded', String(!open));
            toggle.textContent = open ? '☰' : '✕';
        });

        // 메뉴 항목을 고르면 닫는다
        nav.addEventListener('click', function (e) {
            if (e.target.tagName !== 'A') return;
            nav.setAttribute('data-open', 'false');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.textContent = '☰';
        });

        // 데스크톱 폭으로 넓어지면 인라인 상태를 초기화한다
        window.addEventListener('resize', function () {
            if (window.innerWidth > 820) {
                nav.setAttribute('data-open', 'false');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.textContent = '☰';
            }
        });
    }

    /* ---- 시안용 ⚠ 배지 토글 ----
       사진 위 경고 배지를 잠깐 걷어내고 레이아웃만 보고 싶을 때 사용한다.
       선택 상태는 localStorage 에 남겨 페이지를 이동해도 유지된다. */
    var KEY = 'ndn.flagsOff';
    var flagBtn = document.querySelector('[data-flag-toggle]');

    function paint(off) {
        document.body.classList.toggle('flags-off', off);
        if (flagBtn) {
            flagBtn.textContent = off ? '경고 표시 켜기' : '경고 표시 끄기';
            flagBtn.setAttribute('aria-pressed', String(off));
        }
    }

    paint(localStorage.getItem(KEY) === '1');

    if (flagBtn) {
        flagBtn.addEventListener('click', function () {
            var off = !document.body.classList.contains('flags-off');
            localStorage.setItem(KEY, off ? '1' : '0');
            paint(off);
        });
    }

    /* ---- 문의 폼 ----
       정적 시안이라 전송할 백엔드가 없다. 실제로 보내지 않는다는 점을 분명히 알린다. */
    var form = document.querySelector('[data-demo-form]');
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var note = form.querySelector('[data-form-note]');
            if (note) {
                note.hidden = false;
                note.focus();
            }
        });
    }

    /* ---- 푸터 연도 ---- */
    var year = document.querySelector('[data-year]');
    if (year) year.textContent = String(new Date().getFullYear());
})();
