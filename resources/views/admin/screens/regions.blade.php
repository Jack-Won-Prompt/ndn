@extends('admin.screens.layout')
@section('title', '지역별 모집·배치')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">지역별 모집·배치</h1>
            <p class="screen__sub">시군별로 모집 정원과 배치 현황을 나눠 봅니다 · <strong>행을 클릭</strong>하면 해당 지역의 농가별 배치 인원이 열립니다 · 정원·모집 여부는 <strong>농가·지자체</strong> 화면에서 수정</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">지역 현황</button>
        <button type="button" class="screen-tab" data-tab="detail" id="rg-detail-tab" hidden>농가별</button>
    </div>

    <div data-tabpane="list">
        <div class="rg-wrap">
            <table class="rg-table" id="rg-table">
                <thead>
                    <tr>
                        <th>지역</th>
                        <th style="width:80px">모집</th>
                        <th style="width:80px">정원</th>
                        <th style="width:80px">지원자</th>
                        <th style="width:80px">승인대기</th>
                        <th style="width:80px">잔여</th>
                        <th style="width:90px">배치 인원</th>
                        <th style="width:70px">농가</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr data-id="{{ $r['id'] }}">
                            <td><b>{{ $r['name'] }}</b> <span class="rg-region">{{ $r['region'] ?? '' }}</span></td>
                            <td class="c">
                                <span class="rg-badge rg-badge--{{ $r['open'] ? 'open' : 'closed' }}">
                                    {{ $r['open'] ? '모집 중' : ($r['recruiting'] ? '정원 마감' : '중지') }}
                                </span>
                            </td>
                            <td class="c">{{ $r['quota'] ?? '—' }}</td>
                            <td class="c">{{ $r['applicants'] }}</td>
                            <td class="c">{{ $r['pending'] }}</td>
                            <td class="c">{{ $r['remaining'] ?? '—' }}</td>
                            <td class="c"><b>{{ $r['placed'] }}</b></td>
                            <td class="c">{{ $r['farms'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="rg-empty">등록된 지자체가 없습니다. <b>농가·지자체</b> 화면에서 먼저 등록하세요.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div data-tabpane="detail" hidden>
        <div id="rg-detail" class="dtl"><div class="dtl-empty">지역을 클릭하면 농가별 배치 현황이 표시됩니다.</div></div>
    </div>

    <style>
        .rg-wrap{border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .rg-table{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);}
        .rg-table thead th{text-align:left;background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-weight:700;font-size:var(--mv2-fz-xs);padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);white-space:nowrap;}
        .rg-table tbody td{padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);color:var(--mv2-text-strong);}
        .rg-table tbody tr:last-child td{border-bottom:0;}
        .rg-table tbody tr[data-id]{cursor:pointer;}
        .rg-table tbody tr[data-id]:hover{background:var(--mv2-slate-25);}
        .rg-table td.c{text-align:center;}
        .rg-region{color:var(--mv2-text-faint);font-size:var(--mv2-fz-xs);margin-left:6px;}
        .rg-empty{text-align:center;color:var(--mv2-text-faint);padding:34px 0;}
        .rg-badge{display:inline-block;padding:2px 9px;border-radius:100px;font-size:12px;font-weight:700;white-space:nowrap;}
        .rg-badge--open{background:#E7F3F1;color:#12695F;}
        .rg-badge--closed{background:#FDECEC;color:#B42318;}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var BASE = '{{ url('admin/regions') }}';

        function esc(s) { return (s == null ? '' : String(s)); }

        document.getElementById('rg-table').addEventListener('click', function (e) {
            var tr = e.target.closest('tr[data-id]');
            if (!tr) return;

            fetch(BASE + '/' + tr.getAttribute('data-id'), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (d) {
                    var html = '<div class="dtl-head"><b>' + esc(d.label) + ' · 농가별 배치</b>'
                        + '<div class="dtl-head__actions"><button type="button" class="dtl-back" onclick="window.ndnSwitchTab(\'list\')">← 지역 현황</button></div></div>';

                    html += '<dl class="dtl-dl">'
                        + '<dt>모집 정원</dt><dd>' + (d.quota == null ? '제한 없음' : d.quota + '명') + '</dd>'
                        + '<dt>지원자</dt><dd>' + d.applicants + '명</dd>'
                        + '<dt>모집 상태</dt><dd>' + (d.recruiting ? '모집 중' : '중지') + '</dd></dl>';

                    html += '<div class="dtl-sec"><div class="dtl-sec__title">농가 (' + d.farms.length + ')</div>';
                    if (d.farms.length) {
                        html += '<table class="rg-table"><thead><tr><th>농가</th><th style="width:110px">품목</th>'
                            + '<th>주소</th><th style="width:90px">배치 인원</th></tr></thead><tbody>';
                        d.farms.forEach(function (f) {
                            html += '<tr><td>' + esc(f.name) + '</td><td class="c">' + esc(f.main_crop || '—') + '</td>'
                                + '<td>' + esc(f.address || '—') + '</td><td class="c"><b>' + f.placed + '</b></td></tr>';
                        });
                        html += '</tbody></table>';
                    } else {
                        html += '<div class="dtl-empty">이 지역에 등록된 농가가 없습니다.</div>';
                    }
                    html += '</div>';

                    document.getElementById('rg-detail').innerHTML = html;
                    document.getElementById('rg-detail-tab').hidden = false;
                    window.ndnSwitchTab('detail');
                })
                .catch(function () { ndnToast('지역 상세를 불러오지 못했습니다.', { type: 'error' }); });
        });
    })();
</script>
@endsection
