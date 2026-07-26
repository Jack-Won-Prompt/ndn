{{-- 접속자(브라우저)의 타임존을 ndn_tz 쿠키로 심는다. LocalTime 이 이 값으로 시각을 표시한다.
     (CLAUDE.md §11 — 접속한 국가의 시간으로 표시). 서버 렌더 이후 AJAX·다음 페이지부터 적용된다. --}}
<script>
    (function () {
        try {
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            if (tz && ('; ' + document.cookie).indexOf('; ndn_tz=' + tz) === -1) {
                document.cookie = 'ndn_tz=' + tz + ';path=/;max-age=31536000;samesite=lax';
            }
        } catch (e) { /* 실패 시 서버 기본 타임존(Asia/Seoul) 사용 */ }
    })();
</script>
