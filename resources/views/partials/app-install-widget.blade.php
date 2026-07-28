{{-- 회사소개 사이트 좌하단 "모바일 앱 설치" QR 위젯 (데스크톱 전용) --}}
{{--
    플레이스토어를 거치지 않는 배포라 앱을 찾을 경로가 사이트뿐이다.
    우하단은 문의 채팅 위젯이 쓰므로 좌하단에 둔다.

    **화면이 넉넉할 때만 띄운다.** 좁은 화면에서는 하단에 고정된 요소가 페이지의
    CTA 버튼(문의 보내기 등)을 덮어 버린다. 채팅 위젯까지 있으면 하단 띠가 통째로
    가려지므로, 모바일에서는 이 위젯을 띄우지 않고 푸터의 "모바일 앱 설치" 링크로 안내한다.
    (모바일에서는 어차피 자기 화면의 QR 을 찍을 수 없다.)

    정적 라벨은 텍스트 노드로 두어 SiteTranslator 가 방문자 언어로 자동번역한다.
--}}
<aside class="aiw" id="aiw" aria-label="모바일 앱 설치">
    <button type="button" class="aiw-close" id="aiw-close" aria-label="닫기">&times;</button>

    <p class="aiw-title">모바일 앱 설치</p>

    <a class="aiw-qr" href="{{ route('app.download') }}">
        <img src="{{ asset('app/install-qr.svg') }}" alt="앱 설치 QR 코드" width="132" height="132">
    </a>
    <p class="aiw-hint">휴대폰으로 찍으세요</p>
</aside>

<style>
    /* 기본은 숨김. 아래 미디어쿼리에서 여유 있는 화면에만 띄운다. */
    .aiw { display: none; }

    @media (min-width: 721px) and (min-height: 560px) {
        .aiw {
            display: block;
            position: fixed; left: 20px; bottom: 20px; z-index: 9998;
            width: 168px; padding: 14px 12px 12px; text-align: center;
            background: #fff; border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .18);
            font-family: inherit;
        }
        .aiw.is-hidden { display: none; }
    }

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
</style>

<script>
(function () {
    var root = document.getElementById('aiw');
    if (!root) return;

    document.getElementById('aiw-close').addEventListener('click', function () {
        // 이 페이지에서만 감춘다(저장하지 않음) — 다음 페이지에서 다시 보인다.
        root.classList.add('is-hidden');
    });
})();
</script>
