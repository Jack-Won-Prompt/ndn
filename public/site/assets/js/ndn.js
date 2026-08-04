/* ============================================================================
   N.D.N Korea — 웹 공통 스크립트 (2026)
   ----------------------------------------------------------------------------
   의존성 없음. 없어도 페이지는 그대로 동작해야 한다(점진적 향상).
   ========================================================================== */
(function () {
    'use strict';

    /* --- 모바일 메뉴 ------------------------------------------------------ */
    var burger = document.querySelector('[data-nd-burger]');
    var nav = document.getElementById('nd-nav');

    if (burger && nav) {
        burger.addEventListener('click', function () {
            var open = nav.classList.toggle('is-open');
            burger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        // 메뉴 안 링크를 누르면 닫는다(같은 페이지 앵커일 때 열린 채로 남지 않게).
        nav.addEventListener('click', function (e) {
            if (e.target.closest('a')) {
                nav.classList.remove('is-open');
                burger.setAttribute('aria-expanded', 'false');
            }
        });

        // 화면이 넓어지면 열린 상태를 정리한다 — 데스크톱 레이아웃에서 겹쳐 보인다.
        window.addEventListener('resize', function () {
            if (window.innerWidth > 1024) {
                nav.classList.remove('is-open');
                burger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    /* --- 헤더 스크롤 상태 -------------------------------------------------- */
    // 맨 위에서는 경계선 없이 지면과 붙어 있다가, 스크롤하면 선을 그어 떠 있음을 알린다.
    var header = document.querySelector('[data-nd-header]');
    if (header) {
        var onScroll = function () {
            header.classList.toggle('is-stuck', window.scrollY > 4);
        };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }

    /* --- 시안용 폼 --------------------------------------------------------- */
    // 문의 양식은 아직 받는 곳이 없다. 실제로 보내지 않고, 보내지 않았다는 사실을
    // 분명히 알린다 — 보낸 줄 알고 기다리게 두는 것이 가장 나쁘다.
    // (실시간 상담은 우하단 문의 위젯이 실제로 동작한다.)
    Array.prototype.forEach.call(document.querySelectorAll('[data-nd-demoform]'), function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
                return;
            }

            var note = form.querySelector('[data-nd-formnote]');
            if (!note) return;

            note.hidden = false;
            note.focus();
            note.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    });

    /* --- 등장 효과 --------------------------------------------------------- */
    // IntersectionObserver 가 없거나 모션을 줄이도록 설정한 환경에서는 그냥 보여 준다.
    var rises = document.querySelectorAll('.nd-rise');
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!rises.length) {
        return;
    }

    if (reduce || typeof IntersectionObserver === 'undefined') {
        Array.prototype.forEach.call(rises, function (el) { el.classList.add('is-in'); });
        return;
    }

    var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-in');
            io.unobserve(entry.target);
        });
    }, { rootMargin: '120px 0px 0px 0px', threshold: 0 });

    Array.prototype.forEach.call(rises, function (el, i) {
        // 같은 줄에 있는 카드들이 한꺼번에 뜨지 않게 아주 짧은 시차만 준다.
        el.style.transitionDelay = (Math.min(i % 4, 3) * 70) + 'ms';
        io.observe(el);
    });

    // 안전장치 — 관찰이 어떤 이유로든 동작하지 않아도 본문이 숨겨진 채 남지 않게 한다.
    // 정상 동작하면 이미 is-in 이 붙어 있어 아무 일도 일어나지 않는다.
    window.setTimeout(function () {
        Array.prototype.forEach.call(rises, function (el) { el.classList.add('is-in'); });
    }, 2500);
})();


/* ============================================================================
   인터랙션 — 진척 표시 · 제목 등장 · 카드 스포트라이트 · 지표 카운트 · 시차
   ----------------------------------------------------------------------------
   전부 장식이다. 이 블록이 통째로 실패해도 페이지는 읽히고 조작된다.
   모션을 줄이도록 설정한 사람에게는 아무것도 실행하지 않는다.
   ========================================================================== */
(function () {
    'use strict';

    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        return;
    }

    var raf = window.requestAnimationFrame || function (fn) { return setTimeout(fn, 16); };

    /* --- 스크롤 진척 선 ---------------------------------------------------- */
    // 한 화면에 다 들어오는 짧은 페이지에는 띄우지 않는다 — 늘 100%인 선은 잡음이다.
    (function () {
        var docH = document.documentElement.scrollHeight - window.innerHeight;
        if (docH < 400) return;

        var bar = document.createElement('div');
        bar.className = 'nd-progress';
        bar.setAttribute('aria-hidden', 'true');
        document.body.appendChild(bar);

        var ticking = false;
        var paint = function () {
            var max = document.documentElement.scrollHeight - window.innerHeight;
            var p = max > 0 ? Math.min(window.scrollY / max, 1) : 0;
            bar.style.transform = 'scaleX(' + p + ')';
            ticking = false;
        };
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; raf(paint); }
        }, { passive: true });
        paint();
    })();

    /* --- 제목 등장 --------------------------------------------------------- */
    // 자를 상자가 필요하므로 각 줄을 span 으로 감싼다. <br> 을 기준으로 나눈다.
    // 원본 텍스트는 그대로 두고 구조만 덧씌우므로 번역·검색에 영향이 없다.
    (function () {
        // 큰 제목만 대상으로 한다. 카드 안 소제목(.nd-h3)까지 하면 산만해진다.
        // 마크업에 표시를 달지 않아도 되도록 클래스로 찾는다.
        var heads = document.querySelectorAll('.nd-display, .nd-h1, .nd-h2');
        if (!heads.length || typeof IntersectionObserver === 'undefined') return;

        Array.prototype.forEach.call(heads, function (h) {
            // <br> 로 끊어 줄 단위 래퍼를 만든다. 자식 요소(강조 span)는 그대로 옮긴다.
            var lines = [[]];
            Array.prototype.forEach.call(h.childNodes, function (n) {
                if (n.nodeName === 'BR') { lines.push([]); return; }
                lines[lines.length - 1].push(n);
            });
            if (lines.length === 0) return;

            var frag = document.createDocumentFragment();
            lines.forEach(function (nodes) {
                var outer = document.createElement('span');
                outer.className = 'nd-reveal';
                var inner = document.createElement('span');
                nodes.forEach(function (n) { inner.appendChild(n); });
                outer.appendChild(inner);
                frag.appendChild(outer);
            });
            h.innerHTML = '';
            h.appendChild(frag);
        });

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (!e.isIntersecting) return;
                Array.prototype.forEach.call(e.target.querySelectorAll('.nd-reveal'), function (r) {
                    r.classList.add('is-in');
                });
                io.unobserve(e.target);
            });
        }, { rootMargin: '80px 0px 0px 0px', threshold: 0 });

        Array.prototype.forEach.call(heads, function (h) { io.observe(h); });

        // 안전장치 — 관찰이 실패해도 제목이 잘린 채 남지 않게.
        window.setTimeout(function () {
            document.querySelectorAll('.nd-reveal').forEach(function (r) { r.classList.add('is-in'); });
        }, 2500);
    })();

    /* --- 카드 스포트라이트 -------------------------------------------------- */
    // 커서 위치를 CSS 변수로만 넘긴다. 그리기는 CSS 가 한다.
    // 손가락 입력에는 hover 가 없으므로 포인터가 마우스일 때만 건다.
    if (window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
        document.addEventListener('pointermove', function (e) {
            var card = e.target.closest && e.target.closest('.nd-card');
            if (!card) return;
            var r = card.getBoundingClientRect();
            card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
            card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
        }, { passive: true });
    }

    /* --- 지표 카운트 -------------------------------------------------------- */
    // 숫자가 들어 있는 지표만 센다. '○○' 같은 자리표시자는 건드리지 않는다.
    (function () {
        var cells = document.querySelectorAll('.nd-herometa dd, .nd-stat__n');
        if (!cells.length || typeof IntersectionObserver === 'undefined') return;

        var targets = [];
        Array.prototype.forEach.call(cells, function (el) {
            // 첫 텍스트 노드가 숫자여야 한다. 단위(<small>)는 그대로 둔다.
            var node = el.firstChild;
            if (!node || node.nodeType !== 3) return;
            var raw = node.nodeValue.trim();
            if (!/^\d+$/.test(raw)) return;
            targets.push({ node: node, to: parseInt(raw, 10) });
            node.nodeValue = '0';
        });
        if (!targets.length) return;

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (e) {
                if (!e.isIntersecting) return;
                io.unobserve(e.target);
                var t = targets.filter(function (x) { return x.node.parentNode === e.target; })[0];
                if (!t) return;

                var start = null;
                var dur = 1100;
                var step = function (ts) {
                    if (start === null) start = ts;
                    var p = Math.min((ts - start) / dur, 1);
                    // 끝에서 부드럽게 멈춘다
                    var eased = 1 - Math.pow(1 - p, 3);
                    t.node.nodeValue = String(Math.round(t.to * eased));
                    if (p < 1) raf(step);
                };
                raf(step);
            });
        }, { threshold: 0.4 });

        targets.forEach(function (t) { io.observe(t.node.parentNode); });
    })();

    /* --- 히어로 시차 -------------------------------------------------------- */
    // 배경만 스크롤의 일부만큼 움직인다. 히어로가 화면에 있을 때만 계산한다.
    (function () {
        var hero = document.querySelector('.nd-hero');
        if (!hero) return;
        var bg = hero.querySelector('.nd-hero__bg');
        if (!bg) return;

        var ticking = false;
        var paint = function () {
            var y = window.scrollY;
            if (y < hero.offsetHeight) {
                bg.style.transform = 'translate3d(0,' + (y * 0.18) + 'px,0) scale(1.06)';
            }
            ticking = false;
        };
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; raf(paint); }
        }, { passive: true });
        paint();
    })();
})();

