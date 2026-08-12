@php
    use App\Shared\Support\LocalTime;

    /** 값이 없으면 손으로 적도록 빈 칸을 남긴다 — 종이 서식과 같아야 제출본이 된다. */
    $v = fn ($value) => filled($value) ? $value : '';
    $ymd = fn ($d) => $d?->format('Y-m-d') ?? '';
    $yn = fn (?bool $b) => $b === null ? '' : ($b ? '예' : '아니오');
@endphp
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
    @page { margin: 24px 26px 30px; }
    body { color: #1b1e24; font-size: 10.5px; }

    h1 { font-size: 17px; margin: 0 0 3px; text-align: center; }
    .sub { color: #6b7280; font-size: 9.5px; text-align: center; margin-bottom: 14px; }

    h2 { font-size: 11.5px; margin: 14px 0 5px; padding-bottom: 3px; border-bottom: 1.5px solid #1b1e24; }

    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #b9c0ca; padding: 4px 6px; vertical-align: middle; }
    th { background: #f1f4f7; font-weight: bold; text-align: left; }
    /* 라벨 칸 — 종이 서식의 '구분' 열 */
    td.k, th.k { background: #f7f9fb; width: 110px; color: #46505e; }
    td.c, th.c { text-align: center; }
    .fill { min-height: 13px; }

    /* 체크 항목 — 해당 칸에 ● */
    .mark { font-size: 11px; }
    .bad td { background: #fdeceb; }

    .memo { min-height: 34px; }
    .sign img { height: 42px; }
    .sign td { height: 54px; }

    .foot { color: #9aa1ac; font-size: 8.5px; margin-top: 12px; line-height: 1.6; }
    .brand { color: #14807a; font-weight: bold; }
    .note { color: #6b7280; font-size: 9px; }
</style>
</head>
<body>

<h1>외국인근로자 근무상태 종합 점검표</h1>
<div class="sub">
    주식회사 앤디앤 (N.D.N Korea) · 관할 지자체 및 관계기관 제출용 · 생성 {{ $generated_at }}
</div>

{{-- 1. 점검 개요 --}}
<h2>1. 점검 개요</h2>
<table>
    <tr>
        <td class="k">점검일시</td>
        <td>{{ LocalTime::format($review->reviewed_at) }}</td>
        <td class="k">점검유형</td>
        <td>{{ $review->review_type->label() }}</td>
    </tr>
    <tr>
        <td class="k">점검장소</td>
        <td>{{ $v($review->place) }}</td>
        <td class="k">점검자</td>
        <td>{{ $v($review->inspector?->name) }}</td>
    </tr>
</table>

{{-- 2. 농가 및 사업장 정보 --}}
<h2>2. 농가 및 사업장 정보</h2>
<table>
    <tr>
        <td class="k">농가명(사업장명)</td>
        <td>{{ $v($farm?->name) }}</td>
        <td class="k">연락처</td>
        <td>{{ $v($farm?->contact_phone) }}</td>
    </tr>
    <tr>
        <td class="k">소재지</td>
        <td>{{ $v($farm?->address) }}</td>
        <td class="k">재배품목</td>
        <td>{{ $v($farm?->main_crop) }}</td>
    </tr>
    <tr>
        <td class="k">관할 시·군</td>
        <td>{{ $v($farm?->city?->name) }}</td>
        <td class="k">숙소 형태</td>
        <td class="fill"></td>
    </tr>
</table>

{{-- 3. 외국인근로자 기본정보 — 관공서 제출 서식이라 인적사항을 담는다 --}}
<h2>3. 외국인근로자 기본정보</h2>
<table>
    <tr>
        <td class="k">성명(한글)</td>
        <td>{{ $v($worker?->name) }}</td>
        <td class="k">성명(영문)</td>
        <td class="fill"></td>
    </tr>
    <tr>
        <td class="k">국적</td>
        <td>{{ $v($worker?->nationality) }}</td>
        <td class="k">성별</td>
        <td>{{ $v($worker?->gender?->label()) }}</td>
    </tr>
    <tr>
        <td class="k">생년월일(나이)</td>
        <td>{{ $birth_date }}{{ $age !== null ? ' (만 '.$age.'세)' : '' }}</td>
        <td class="k">여권번호</td>
        <td>{{ $v($passport_no) }}</td>
    </tr>
    <tr>
        <td class="k">비자종류</td>
        <td>E-8</td>
        <td class="k">비자번호</td>
        <td class="fill"></td>
    </tr>
    <tr>
        <td class="k">입국일</td>
        <td>{{ $entered_on ? LocalTime::format($entered_on, 'Y-m-d') : '' }}</td>
        <td class="k">근로계약기간</td>
        <td>{{ $ymd($contract_from) }}{{ $contract_from || $contract_to ? ' ~ ' : '' }}{{ $ymd($contract_to) }}</td>
    </tr>
    <tr>
        <td class="k">연락처</td>
        <td class="fill"></td>
        <td class="k">비상연락망(본국)</td>
        <td>{{ $v($phone_home) }}</td>
    </tr>
</table>

{{-- 4~7. 점검 항목 --}}
@foreach ($sections as $i => $section)
    <h2>{{ $i + 4 }}. {{ $section['label'] }}</h2>
    <table>
        <thead>
            <tr>
                <th>점검항목</th>
                @foreach ($section['options'] as $label)
                    <th class="c" style="width:62px">{{ $label }}</th>
                @endforeach
                <th style="width:130px">비고</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($section['rows'] as $row)
                <tr class="{{ $row['bad'] ? 'bad' : '' }}">
                    <td>{{ $row['label'] }}</td>
                    @foreach ($section['options'] as $value => $label)
                        <td class="c mark">{{ $row['value'] === $value ? '●' : '' }}</td>
                    @endforeach
                    <td>{{ $v($row['note']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endforeach

{{-- 연장근무 --}}
<h2>8. 연장근무 내역</h2>
<table>
    <tr>
        <td class="k">실시 여부</td>
        <td>{{ $yn($review->overtime_done) }}</td>
        <td class="k">연장근무 시간</td>
        <td>{{ $review->overtime_hours !== null ? $review->overtime_hours.' 시간' : '' }}</td>
        <td class="k">근로자 동의</td>
        <td>{{ $yn($review->overtime_consented) }}</td>
    </tr>
</table>

{{-- 임금 및 계약사항 --}}
<h2>9. 임금 및 계약사항 확인</h2>
<table>
    <tr>
        <td class="k">월 평균 임금</td>
        <td>{{ $v($review->avg_monthly_wage) }}</td>
        <td class="k">최근 임금 지급일</td>
        <td>{{ $ymd($review->last_paid_on) }}</td>
    </tr>
    <tr>
        <td class="k">임금 체불 여부</td>
        <td>{{ $review->wage_unpaid ? '있음' : '없음' }}</td>
        <td class="k">숙식 제공 여부</td>
        <td>{{ $yn($review->board_provided) }}</td>
    </tr>
    <tr>
        <td class="k">근로계약 준수</td>
        <td>{{ $yn($review->contract_followed) }}</td>
        <td class="k">계약 위반 사항</td>
        <td>{{ $v($review->contract_violation) }}</td>
    </tr>
</table>

{{-- 종합 의견 --}}
<h2>10. 종합 의견</h2>
<table>
    <tr>
        <td class="k">점검 결과</td>
        <td>{{ $review->result->label() }}</td>
        <td class="k">이탈 리스크</td>
        <td>{{ $review->risk_level->label() }} ({{ $review->risk_score }}점)</td>
    </tr>
    <tr><td class="k">주요 특이사항</td><td class="memo" colspan="3">{{ $v($review->notable) }}</td></tr>
    <tr><td class="k">개선 요구사항</td><td class="memo" colspan="3">{{ $v($review->improvements) }}</td></tr>
    <tr><td class="k">농가 건의사항</td><td class="memo" colspan="3">{{ $v($review->farm_requests) }}</td></tr>
</table>

{{-- 향후 조치사항 --}}
<h2>11. 향후 조치사항</h2>
<table>
    <tr>
        <td class="k">개선기한</td>
        <td>{{ $ymd($review->action_due_on) }}</td>
        <td class="k">담당자</td>
        <td>{{ $v($review->action_assignee) }}</td>
    </tr>
    <tr>
        <td class="k">재점검 예정일</td>
        <td>{{ $ymd($review->recheck_on) }}</td>
        <td class="k">보고 필요</td>
        <td>
            지자체 {{ $review->report_city ? '예' : '아니오' }} ·
            출입국사무소 {{ $review->report_immigration ? '예' : '아니오' }}
        </td>
    </tr>
    <tr><td class="k">기타 조치사항</td><td class="memo" colspan="3">{{ $v($review->action_note) }}</td></tr>
</table>

{{-- 확인 및 서명 --}}
<h2>12. 확인 및 서명</h2>
<table class="sign">
    <thead>
        <tr>
            <th class="c" style="width:110px">구분</th>
            <th style="width:150px">성명</th>
            <th>서명</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($signatures as $s)
            <tr>
                <td class="c k">{{ $s['label'] }}</td>
                <td>{{ $v($s['name']) }}</td>
                <td>@if ($s['image'])<img src="{{ $s['image'] }}" alt="">@endif</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="foot">
    ※ 본 점검표는 외국인근로자의 근무·생활·안전·비자관리 실태를 확인하기 위한 자료로,
    관할 지자체 및 관계기관의 요청 시 제출할 수 있습니다.<br>
    본 문서는 <span class="brand">N.D.N Korea 운영 콘솔</span>에서 생성되었으며,
    개인정보가 포함되어 있으므로 취급에 유의하십시오. 빈칸은 현장에서 직접 기재합니다.
</div>

</body>
</html>
