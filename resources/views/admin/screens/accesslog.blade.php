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

    <div class="signup-wrap">
        <table class="signup-table" id="al-table">
            <thead>
                <tr>
                    <th style="width:230px">시각 ({{ $displayTz }})</th>
                    <th style="width:220px">사용자 · 로그인 ID</th>
                    <th style="width:60px">방식</th>
                    <th>경로</th>
                    <th style="width:60px">상태</th>
                    <th style="width:120px">IP</th>
                    <th style="width:110px">접속 국가</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr data-auth="{{ $r['is_guest'] ? '0' : '1' }}"
                        data-foreign="{{ $r['is_foreign'] ? '1' : '0' }}"
                        data-search="{{ strtolower(($r['actor'] ?? '').' '.($r['email'] ?? '').' '.$r['path'].' '.($r['ip'] ?? '').' '.$r['country_label']) }}">
                        <td class="c">
                            {{ $r['at'] }}
                            {{-- 상대 시간을 함께 둔다. 방금 한 일이 '9시간 전'으로 보이면
                                 저장·표시 타임존이 어긋난 것이라 여기서 바로 드러난다. --}}
                            <div class="al-ago">{{ $r['ago'] }}</div>
                        </td>
                        <td>
                            @if ($r['is_guest'])
                                <span class="al-tag al-tag--guest">게스트</span>
                            @else
                                <span class="al-tag al-tag--auth">{{ $r['actor'] }}</span>
                                @if ($r['email'])
                                    <div class="al-email">{{ $r['email'] }}</div>
                                @endif
                            @endif
                        </td>
                        <td class="c">{{ $r['method'] }}</td>
                        <td class="al-path" title="{{ $r['route'] }}">{{ $r['path'] }}</td>
                        <td class="c">
                            <span class="al-status al-status--{{ $r['status'] >= 400 ? 'err' : ($r['status'] >= 300 ? 'redir' : 'ok') }}">{{ $r['status'] }}</span>
                        </td>
                        <td class="c">{{ $r['ip'] }}</td>
                        <td class="c">
                            <span class="al-country {{ $r['is_foreign'] ? 'al-country--warn' : ($r['country'] === null ? 'al-country--none' : '') }}">
                                {{ $r['country_label'] }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr id="al-empty"><td colspan="7" class="su-empty">기록된 접속 로그가 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

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
        .al-ago { font-size: 11px; color: var(--mv2-text-faint); margin-top: 2px; }
        .al-country { font-size: 12px; font-weight: 700; }
        .al-country--warn { color: var(--mv2-pill-err-fg); }
        .al-country--none { color: var(--mv2-text-faint); font-weight: 500; }
        .signup-wrap { border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-lg); overflow: hidden; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 6px 20px rgba(15,23,42,.05); }
        .signup-table { width: 100%; border-collapse: collapse; font-size: var(--mv2-fz-sm); }
        .signup-table thead th { text-align: left; background: var(--mv2-slate-25); color: var(--mv2-text-muted); font-weight: 700; font-size: var(--mv2-fz-xs); padding: 10px 14px; border-bottom: 1px solid var(--mv2-border-soft); white-space: nowrap; }
        .signup-table tbody td { padding: 9px 14px; border-bottom: 1px solid var(--mv2-border-soft); color: var(--mv2-text-strong); }
        .signup-table tbody tr:last-child td { border-bottom: 0; }
        .signup-table tbody tr:hover { background: var(--mv2-slate-25); }
        .signup-table td.c { text-align: center; }
        .al-path { font-family: ui-monospace, "SFMono-Regular", Menlo, monospace; font-size: var(--mv2-fz-xs); color: var(--mv2-text-strong); word-break: break-all; }
        .su-empty { text-align: center; color: var(--mv2-text-faint); padding: 34px 0; }
        .al-tag { display: inline-block; font-size: 11px; font-weight: 700; border-radius: 100px; padding: 2px 9px; }
        .al-tag--guest { background: var(--mv2-slate-25); color: var(--mv2-text-muted); }
        .al-tag--auth { background: var(--mv2-primary-50, #E9F6F4); color: var(--mv2-primary-600); }
        .al-email { font-size: 11px; color: var(--mv2-text-muted); margin-top: 3px; font-family: ui-monospace, "SFMono-Regular", Menlo, monospace; }
        .al-status { font-weight: 700; font-size: 12px; }
        .al-status--ok { color: #1B7F43; }
        .al-status--redir { color: #8a6d00; }
        .al-status--err { color: var(--mv2-pill-err-fg); }
    </style>
@endsection

@section('script')
<script>
    (function () {
        var search = document.getElementById('al-search');
        var authOnly = document.getElementById('al-authonly');
        var foreignOnly = document.getElementById('al-foreignonly');
        var rows = [].slice.call(document.querySelectorAll('#al-table tbody tr[data-search]'));

        function apply() {
            var q = search.value.trim().toLowerCase();
            var onlyAuth = authOnly.checked;
            var onlyForeign = foreignOnly.checked;
            rows.forEach(function (tr) {
                var okText = !q || tr.getAttribute('data-search').indexOf(q) !== -1;
                var okAuth = !onlyAuth || tr.getAttribute('data-auth') === '1';
                var okForeign = !onlyForeign || tr.getAttribute('data-foreign') === '1';
                tr.style.display = (okText && okAuth && okForeign) ? '' : 'none';
            });
        }
        var t;
        search.addEventListener('input', function () { clearTimeout(t); t = setTimeout(apply, 150); });
        authOnly.addEventListener('change', apply);
        foreignOnly.addEventListener('change', apply);
    })();
</script>
@endsection
