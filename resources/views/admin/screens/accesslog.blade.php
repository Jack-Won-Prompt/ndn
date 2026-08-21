@extends('admin.screens.layout')
@section('title', '접속 로그')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">접속 로그</h1>
            <p class="screen__sub">
                회사소개(메인) 비로그인 접속 + 로그인 이후 콘솔·포털 페이지 접근 기록 · 최근 {{ count($rows) }}건 ·
                접속 IP의 <strong>국가</strong>까지만 남기며 좌표는 저장하지 않습니다(§7-2)
            </p>
        </div>
    </div>

    <div class="al-summary">
        <div class="al-card"><span class="al-card__n">{{ number_format($summary['total']) }}</span><span class="al-card__l">전체</span></div>
        <div class="al-card"><span class="al-card__n">{{ number_format($summary['today']) }}</span><span class="al-card__l">오늘</span></div>
        <div class="al-card"><span class="al-card__n">{{ number_format($summary['auth']) }}</span><span class="al-card__l">로그인</span></div>
        <div class="al-card"><span class="al-card__n">{{ number_format($summary['guest']) }}</span><span class="al-card__l">게스트</span></div>
        <div class="al-card {{ $summary['foreign'] > 0 ? 'al-card--warn' : '' }}">
            <span class="al-card__n">{{ number_format($summary['foreign']) }}</span><span class="al-card__l">해외</span>
        </div>
    </div>

    @if (! $hasGeoData)
        <div class="al-note">
            국가 판별표가 없어 <b>내부</b>(사내·같은 장비)만 가려내고 나머지는 <b>미상</b>으로 남습니다.
            <code>storage/app/geoip/ip-country.csv</code> 에 무료 국가 대역표(DB-IP Lite·IP2Location LITE 등)를
            넣으면 이후 접속부터 나라가 기록됩니다. 이미 쌓인 기록은 접속 당시 판단을 그대로 둡니다.
        </div>
    @endif

    @if ($byCountry)
        <div class="al-countries">
            @foreach ($byCountry as $c)
                <span class="al-chip {{ $c['foreign'] ? 'al-chip--warn' : '' }}">
                    {{ $c['label'] }} <b>{{ number_format($c['count']) }}</b>
                </span>
            @endforeach
        </div>
    @endif

    <div class="al-toolbar">
        <input type="search" id="al-search" placeholder="사용자·경로·IP·국가 검색">
        <label class="al-only"><input type="checkbox" id="al-authonly"> 로그인만</label>
        <label class="al-only"><input type="checkbox" id="al-foreignonly"> 해외만</label>
        <span class="al-tz">시각 기준 <b>{{ $displayTz }}</b> — 보는 사람의 지역 시간으로 표시합니다</span>
    </div>

    <div id="grid-accesslog"></div>

    <style>
        .al-summary { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
        .al-card { flex: 1; min-width: 120px; background: #fff; border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-lg); padding: 14px 16px; display: flex; flex-direction: column; gap: 2px; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .al-card__n { font-size: 22px; font-weight: 800; color: var(--mv2-text-strong); }
        .al-card__l { font-size: var(--mv2-fz-xs); color: var(--mv2-text-muted); font-weight: 700; }
        .al-toolbar { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; }
        .al-toolbar input[type=search] { flex: 0 0 320px; max-width: 60%; font-family: inherit; font-size: var(--mv2-fz-sm); padding: 8px 12px; border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-sm); }
        .al-toolbar input[type=search]:focus { outline: none; border-color: var(--mv2-primary-500); box-shadow: 0 0 0 3px rgba(30,156,146,.15); }
        .al-only { display: flex; align-items: center; gap: 6px; font-size: var(--mv2-fz-sm); color: var(--mv2-text-muted); cursor: pointer; }
        .al-card--warn .al-card__n { color: var(--mv2-pill-err-fg); }
        .al-tz { margin-left: auto; font-size: var(--mv2-fz-xs); color: var(--mv2-text-muted); }
        .al-tz b { color: var(--mv2-text-strong); }
        .al-note { background: #FFF8E6; border: 1px solid #F0DFAE; color: #6B5200; border-radius: var(--mv2-r-lg);
            padding: 12px 15px; margin-bottom: 12px; font-size: var(--mv2-fz-sm); line-height: 1.7; }
        .al-note code { background: #fff; border: 1px solid #EADFBF; border-radius: 4px; padding: 1px 6px; }
        .al-countries { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 12px; }
        .al-chip { font-size: var(--mv2-fz-xs); background: var(--mv2-slate-25); border: 1px solid var(--mv2-border-soft);
            border-radius: 100px; padding: 4px 11px; color: var(--mv2-text-muted); }
        .al-chip b { color: var(--mv2-text-strong); margin-left: 3px; }
        .al-chip--warn { background: var(--mv2-pill-err-bg); border-color: transparent; color: var(--mv2-pill-err-fg); }
        .al-chip--warn b { color: var(--mv2-pill-err-fg); }
    </style>
@endsection

@section('wwgrid')
<script>
    var AL_ROWS = @json($rows, JSON_UNESCAPED_UNICODE);

    // 접속 로그는 **읽기 전용**이다. 감사 기록을 손으로 고칠 수 있으면 §7-6 의 뜻이
    // 사라진다. 그래서 [신규 행]·[행 삭제]·[변경 저장] 없이 엑셀 다운로드만 둔다.
    var alGrid = wwConsole({
        el: 'grid-accesslog',
        title: '접속로그',
        data: AL_ROWS,
        columns: [
            { header: '시각 ({{ $displayTz }})', name: 'at', width: 165, align: 'center', sortable: true },
            // 방금 한 일이 '9시간 전' 으로 보이면 저장·표시 타임존이 어긋난 것이라 여기서 드러난다.
            { header: '경과', name: 'ago', width: 90, align: 'center' },
            { header: '사용자', name: 'actor', width: 140, sortable: true },
            { header: '로그인 ID', name: 'email', width: 190 },
            { header: '방식', name: 'method', width: 66, align: 'center' },
            { header: '경로', name: 'path', width: 300 },
            { header: '상태', name: 'status', width: 60, align: 'center', sortable: true },
            { header: 'IP', name: 'ip', width: 125, align: 'center' },
            { header: '접속 국가', name: 'country_label', width: 100, align: 'center', sortable: true },
        ],
    });

    (function () {
        var search = document.getElementById('al-search');
        var authOnly = document.getElementById('al-authonly');
        var foreignOnly = document.getElementById('al-foreignonly');

        // 표 자체를 걸러 다시 그린다. 행을 숨기는 방식은 그리드의 줄 번호·건수와
        // 어긋나고, 엑셀 다운로드에는 숨긴 줄까지 따라간다.
        function apply() {
            var q = search.value.trim().toLowerCase();
            alGrid.setData(AL_ROWS.filter(function (r) {
                if (authOnly.checked && r.is_guest) return false;
                if (foreignOnly.checked && !r.is_foreign) return false;
                if (!q) return true;
                return [r.actor, r.email, r.path, r.ip, r.country_label]
                    .join(' ').toLowerCase().indexOf(q) !== -1;
            }));
        }

        var t;
        search.addEventListener('input', function () { clearTimeout(t); t = setTimeout(apply, 150); });
        authOnly.addEventListener('change', apply);
        foreignOnly.addEventListener('change', apply);
    })();
</script>
@endsection
