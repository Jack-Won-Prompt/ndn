<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<style>
    /* 한글 임베드 폰트 (dompdf 는 기본 폰트에 한글 글리프가 없어 깨짐) */
    @font-face {
        font-family: 'NanumGothic';
        font-style: normal;
        font-weight: normal;
        src: url('{{ str_replace('\\', '/', resource_path('fonts/NanumGothic.ttf')) }}') format('truetype');
    }
    @font-face {
        font-family: 'NanumGothic';
        font-style: normal;
        font-weight: bold;
        src: url('{{ str_replace('\\', '/', resource_path('fonts/NanumGothic-Bold.ttf')) }}') format('truetype');
    }
    * { font-family: 'NanumGothic', sans-serif; }
    body { color: #1b1e24; font-size: 12px; }
    h1 { font-size: 20px; margin: 0 0 2px; }
    .sub { color: #6b7280; font-size: 11px; margin-bottom: 18px; }
    .box { border: 1px solid #d5dbe3; border-radius: 6px; padding: 0; margin-bottom: 14px; }
    .box h2 { font-size: 13px; margin: 0; padding: 8px 12px; background: #f4f6f8; border-bottom: 1px solid #d5dbe3; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 8px 12px; border-bottom: 1px solid #eaedf1; }
    td.k { color: #5c6878; width: 55%; }
    td.v { text-align: right; font-weight: bold; }
    tr:last-child td { border-bottom: 0; }
    .foot { color: #9aa1ac; font-size: 10px; margin-top: 18px; }
    .brand { color: #14807a; font-weight: bold; }
</style>
</head>
<body>
    <h1>{{ $r['year'] }}년 {{ $r['month'] }}월 계절근로자 관리 월간 보고</h1>
    <div class="sub">주식회사 앤디앤 (N.D.N Korea) · 지자체 제출용 · 생성 {{ $r['generated_at'] }}</div>

    <div class="box">
        <h2>인원 및 점검 현황</h2>
        <table>
            <tr><td class="k">재직 근로자</td><td class="v">{{ number_format($r['active_workers']) }} 명</td></tr>
            <tr><td class="k">당월 근무상태 점검 실시</td><td class="v">{{ number_format($r['interview_total']) }} 건</td></tr>
        </table>
    </div>

    <div class="box">
        <h2>이탈 리스크 (행동 신호 기반, 위치 추적 미사용)</h2>
        <table>
            <tr><td class="k">고위험</td><td class="v">{{ number_format($r['risk_high']) }} 명</td></tr>
            <tr><td class="k">주의</td><td class="v">{{ number_format($r['risk_medium']) }} 명</td></tr>
        </table>
    </div>

    <div class="box">
        <h2>민원 처리</h2>
        <table>
            <tr><td class="k">당월 접수</td><td class="v">{{ number_format($r['tickets_total']) }} 건</td></tr>
            <tr><td class="k">미처리(접수 상태)</td><td class="v">{{ number_format($r['tickets_open']) }} 건</td></tr>
        </table>
    </div>

    <div class="foot">
        본 보고서는 <span class="brand">N.D.N Korea 운영 콘솔</span>에서 자동 생성되었습니다.
        개인정보는 포함하지 않으며 집계 수치만 제공합니다.
    </div>
</body>
</html>
