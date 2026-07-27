{{-- 회사소개 사이트 좌하단 "모바일 앱 설치" 위젯 --}}
{{--
    플레이스토어를 거치지 않는 배포라 앱을 찾을 경로가 사이트뿐이다.
    우하단은 문의 채팅 위젯이 쓰므로 좌하단에 둔다.

    QR 은 접지 않고 **항상 노출**한다. 대신 콘텐츠를 가리지 않도록 카드를 작게 두고,
    닫기(×)로 그 페이지에서만 감출 수 있게 한다(저장하지 않으므로 다음 페이지에서 다시 보인다).

    데스크톱: QR (폰으로 찍어 설치)
    모바일:  QR 대신 설치 페이지 링크 — 자기 화면의 QR 은 찍을 수 없다.

    정적 라벨은 텍스트 노드로 두어 SiteTranslator 가 방문자 언어로 자동번역한다.
--}}
<aside class="aiw" id="aiw" aria-label="모바일 앱 설치">
    <button type="button" class="aiw-close" id="aiw-close" aria-label="닫기">&times;</button>

    <p class="aiw-title">모바일 앱 설치</p>

    {{-- 데스크톱 — QR 상시 노출 --}}
    <a class="aiw-qr" href="{{ url('/app/') }}">
        <img src="{{ asset('app/install-qr.png') }}" alt="앱 설치 페이지 QR 코드" width="132" height="132">
    </a>
    <p class="aiw-hint">휴대폰으로 찍으세요</p>

    {{-- 모바일 — 바로 이동 --}}
    <a class="aiw-cta" href="{{ url('/app/') }}">설치 페이지 열기</a>
</aside>

<style>
    .aiw {
        position: fixed; left: 20px; bottom: 20px; z-index: 9998;
        width: 168px; padding: 14px 12px 12px; text-align: center;
        background: #fff; border-radius: 14px;
        box-shadow: 0 10px 30px rgba(15, 23, 42, .18);
        font-family: inherit;
    }
    .aiw.is-hidden { display: none; }

    .aiw-close {
        position: absolute; top: 4px; right: 6px;
        border: 0; background: transparent; color: #9AA1AC;
        font-size: 18px; line-height: 1; cursor: pointer; padding: 2px 4px;
    }
    .aiw-close:hover { color: #1A140F; }

    .aiw-title { margin: 0 0 10px; font-size: 13px; font-weight: 700; color: #1A140F; }

    .aiw-qr { display: block; }
    .aiw-qr img { width: 132px; height: 132px; display: block; margin: 0 auto; }

    .aiw-hint { margin: 8px 0 0; font-size: 11px; color: #6B7280; }

    .aiw-cta {
        display: none; padding: 11px 14px;
        background: #0A0A0A; color: #fff; font-weight: 700; font-size: 14px;
        border-radius: 9px; text-decoration: none;
    }

    /* 모바일: QR 은 쓸 수 없으므로 링크 버튼으로 바꾸고 카드를 더 줄인다. */
    @media (max-width: 720px) {
        .aiw { left: 12px; bottom: 12px; width: auto; padding: 10px; }
        .aiw-title, .aiw-qr, .aiw-hint { display: none; }
        .aiw-cta { display: block; }
        .aiw-close { display: none; }
    }

    /* 세로가 짧은 화면(가로 모드 등)에서는 QR 이 화면을 다 덮는다 — 링크로 대체. */
    @media (max-height: 520px) {
        .aiw { width: auto; padding: 10px; }
        .aiw-title, .aiw-qr, .aiw-hint { display: none; }
        .aiw-cta { display: block; }
    }
</style>

<script>
(function () {
    var root = document.getElementById('aiw');
    if (!root) return;

    document.getElementById('aiw-close').addEventListener('click', function () {
        // 이 페이지에서만 감춘다(저장하지 않음) — 다음 페이지에서 다시 보인다.
        root.classList.add('is-hidden');
    });

    // 좁은 화면에서 채팅 위젯과 겹치지 않도록, 채팅이 열리면 잠시 감춘다.
    document.addEventListener('click', function (e) {
        if (!window.matchMedia('(max-width: 720px)').matches) return;
        if (e.target.closest('#cw-launch')) root.classList.add('is-hidden');
        if (e.target.closest('#cw-close')) root.classList.remove('is-hidden');
    });
})();
</script>
