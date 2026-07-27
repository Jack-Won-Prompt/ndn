{{-- 회사소개 사이트 좌하단 "근로자 앱 설치" 위젯 --}}
{{--
    플레이스토어를 거치지 않는 배포라 앱을 찾을 경로가 사이트뿐이다.
    우하단은 문의 채팅 위젯이 쓰므로 좌하단에 둔다.

    데스크톱: QR 을 보여 준다 (폰으로 찍어 설치).
    모바일:  QR 대신 설치 페이지 링크를 준다 — 자기 화면의 QR 은 찍을 수 없다.

    정적 라벨은 텍스트 노드로 두어 SiteTranslator 가 방문자 언어로 자동번역한다.
--}}
<div class="aiw" id="aiw">
    {{-- 런처 --}}
    <button type="button" class="aiw-launch" id="aiw-launch" aria-expanded="false" aria-controls="aiw-panel">
        <span class="aiw-launch__icon" aria-hidden="true">📱</span>
        <span class="aiw-launch__label">근로자 앱 설치</span>
    </button>

    {{-- 패널 --}}
    <section class="aiw-panel" id="aiw-panel" hidden aria-label="근로자 앱 설치">
        <header class="aiw-head">
            <b>근로자 앱</b>
            <button type="button" class="aiw-close" id="aiw-close" aria-label="닫기">&times;</button>
        </header>

        {{-- 데스크톱 — QR --}}
        <div class="aiw-qr">
            <img src="{{ asset('app/install-qr.png') }}" alt="앱 설치 페이지 QR 코드" width="150" height="150">
            <p class="aiw-hint">휴대폰 카메라로 찍으면 설치 페이지가 열립니다.</p>
        </div>

        {{-- 모바일 — 바로 이동 (자기 화면의 QR 은 찍을 수 없다) --}}
        <a class="aiw-cta" href="{{ url('/app/') }}">설치 페이지 열기</a>

        <p class="aiw-note">안드로이드 전용 · 계정은 담당자에게 문의하세요.</p>
    </section>
</div>

<style>
    .aiw { position: fixed; left: 20px; bottom: 20px; z-index: 9998; font-family: inherit; }

    .aiw-launch { display: inline-flex; align-items: center; gap: 8px; border: 0; cursor: pointer;
        background: #0A0A0A; color: #fff; font-family: inherit; font-size: 15px; font-weight: 700;
        padding: 12px 18px; border-radius: 999px; box-shadow: 0 8px 24px rgba(15,23,42,.22);
        transition: transform .15s, box-shadow .15s; }
    .aiw-launch:hover { transform: translateY(-2px); box-shadow: 0 12px 30px rgba(10,10,10,.35); }
    .aiw-launch__icon { font-size: 18px; line-height: 1; }
    .aiw.is-open .aiw-launch { display: none; }

    .aiw-panel { width: 240px; background: #fff; border-radius: 16px; overflow: hidden;
        box-shadow: 0 18px 48px rgba(15,23,42,.26); }
    .aiw-head { display: flex; align-items: center; justify-content: space-between;
        padding: 12px 14px; background: #0A0A0A; color: #fff; font-size: 15px; }
    .aiw-close { border: 0; background: transparent; color: #fff; font-size: 22px;
        line-height: 1; cursor: pointer; padding: 0 2px; }

    .aiw-qr { padding: 16px 14px 6px; text-align: center; }
    .aiw-qr img { width: 150px; height: 150px; display: block; margin: 0 auto; }
    .aiw-hint { margin: 10px 0 0; font-size: 12px; line-height: 1.5; color: #6B7280; }

    .aiw-cta { display: none; margin: 14px; padding: 13px 16px; text-align: center;
        background: #57D870; color: #062B10; font-weight: 700; font-size: 15px;
        border-radius: 10px; text-decoration: none; }

    .aiw-note { margin: 0; padding: 10px 14px 14px; font-size: 11px; color: #9AA1AC; text-align: center; }

    /* 모바일: QR 을 숨기고 링크 버튼을 보여 준다.
       화면이 좁으므로 런처도 아이콘만 남긴다. */
    @media (max-width: 720px) {
        .aiw { left: 14px; bottom: 14px; }
        .aiw-launch__label { display: none; }
        .aiw-launch { padding: 12px 14px; }
        .aiw-qr { display: none; }
        .aiw-cta { display: block; }
        .aiw-panel { width: 200px; }
    }
</style>

<script>
(function () {
    var root = document.getElementById('aiw');
    if (!root) return;

    var launch = document.getElementById('aiw-launch');
    var panel = document.getElementById('aiw-panel');
    var close = document.getElementById('aiw-close');

    function setOpen(open) {
        root.classList.toggle('is-open', open);
        panel.hidden = !open;
        launch.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    launch.addEventListener('click', function () { setOpen(true); });
    close.addEventListener('click', function () { setOpen(false); });

    // 좁은 화면에서 두 위젯이 겹치지 않도록, 채팅이 열리면 이쪽은 접는다.
    document.addEventListener('click', function (e) {
        if (e.target.closest('#cw-launch')) setOpen(false);
    });
})();
</script>
