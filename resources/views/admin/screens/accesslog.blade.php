@extends('admin.screens.layout')
@section('title', '접속 로그')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">접속 로그</h1>
            <p class="screen__sub">회사소개(메인) 비로그인 접속 + 로그인 이후 콘솔·포털 페이지 접근 기록 · 최근 {{ count($rows) }}건 · 위치정보는 저장하지 않습니다(§7-2)</p>
        </div>
    </div>

    <div class="al-summary">
        <div class="al-card"><span class="al-card__n">{{ number_format($summary['total']) }}</span><span class="al-card__l">전체</span></div>
        <div class="al-card"><span class="al-card__n">{{ number_format($summary['today']) }}</span><span class="al-card__l">오늘</span></div>
        <div class="al-card"><span class="al-card__n">{{ number_format($summary['auth']) }}</span><span class="al-card__l">로그인</span></div>
        <div class="al-card"><span class="al-card__n">{{ number_format($summary['guest']) }}</span><span class="al-card__l">게스트</span></div>
    </div>

    <div class="al-toolbar">
        <input type="search" id="al-search" placeholder="사용자·경로·IP 검색">
        <label class="al-only"><input type="checkbox" id="al-authonly"> 로그인만</label>
    </div>

    <div class="signup-wrap">
        <table class="signup-table" id="al-table">
            <thead>
                <tr>
                    <th style="width:150px">시각</th>
                    <th style="width:220px">사용자 · 로그인 ID</th>
                    <th style="width:60px">방식</th>
                    <th>경로</th>
                    <th style="width:60px">상태</th>
                    <th style="width:120px">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr data-auth="{{ $r['is_guest'] ? '0' : '1' }}"
                        data-search="{{ strtolower(($r['actor'] ?? '').' '.($r['email'] ?? '').' '.$r['path'].' '.($r['ip'] ?? '')) }}">
                        <td class="c">{{ $r['at'] }}</td>
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
                    </tr>
                @empty
                    <tr id="al-empty"><td colspan="6" class="su-empty">기록된 접속 로그가 없습니다.</td></tr>
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
        var rows = [].slice.call(document.querySelectorAll('#al-table tbody tr[data-search]'));

        function apply() {
            var q = search.value.trim().toLowerCase();
            var onlyAuth = authOnly.checked;
            rows.forEach(function (tr) {
                var okText = !q || tr.getAttribute('data-search').indexOf(q) !== -1;
                var okAuth = !onlyAuth || tr.getAttribute('data-auth') === '1';
                tr.style.display = (okText && okAuth) ? '' : 'none';
            });
        }
        var t;
        search.addEventListener('input', function () { clearTimeout(t); t = setTimeout(apply, 150); });
        authOnly.addEventListener('change', apply);
    })();
</script>
@endsection
