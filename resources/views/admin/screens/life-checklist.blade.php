@extends('admin.screens.layout')
@section('title', '생활 체크리스트')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">생활 체크리스트</h1>
            <p class="screen__sub">입국 후 1주일 이내 확인사항 · 근로자가 앱에서 직접 체크 · 덜 된 사람이 위에 옵니다</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">확인 현황</button>
        <button type="button" class="screen-tab" data-tab="items">항목 관리</button>
    </div>

    <div data-tabpane="list">
        <div id="grid-lifechecklist"></div>
    </div>

    <div data-tabpane="items" hidden>
        <p class="lc-note">
            문구를 고치면 근로자 앱에 바로 반영됩니다. 번역은 자동이라 <b>한국어만 고치면 됩니다.</b><br>
            항목을 빼야 하면 <b>[중지]</b>로 내리십시오 — 지우면 근로자가 확인한 기록도 함께 사라집니다.
        </p>
        <div class="lc-wrap">
            <table class="lc-table">
                <thead>
                    <tr>
                        <th style="width:52px">순서</th>
                        <th style="width:150px">코드</th>
                        <th>문구</th>
                        <th>설명(선택)</th>
                        <th style="width:96px">사용</th>
                        <th style="width:84px"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($itemRows as $i => $r)
                        <tr data-id="{{ $r['id'] }}">
                            <td class="c">{{ $i + 1 }}</td>
                            <td><span class="lc-code">{{ $r['code'] }}</span></td>
                            <td><input type="text" data-field="label" maxlength="200" value="{{ $r['label'] }}"></td>
                            <td><input type="text" data-field="hint" maxlength="300" value="{{ $r['hint'] }}"></td>
                            <td class="c">
                                <select data-field="active">
                                    <option value="1" @selected($r['active'] === '사용')>사용</option>
                                    <option value="0" @selected($r['active'] !== '사용')>중지</option>
                                </select>
                            </td>
                            <td class="c"><button type="button" class="lc-btn" data-save>저장</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .lc-note{font-size:var(--mv2-fz-sm);color:var(--mv2-text-muted);background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:14px 16px;margin:0 0 14px;line-height:1.8;}
        .lc-note b{color:var(--mv2-text-strong);}
        .lc-wrap{border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .lc-table{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);}
        .lc-table thead th{text-align:left;background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-weight:700;font-size:var(--mv2-fz-xs);padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);white-space:nowrap;}
        .lc-table tbody td{padding:8px 12px;border-bottom:1px solid var(--mv2-border-soft);color:var(--mv2-text-strong);vertical-align:middle;}
        .lc-table tbody tr:last-child td{border-bottom:0;}
        .lc-table td.c{text-align:center;}
        .lc-table input,.lc-table select{width:100%;font-family:inherit;font-size:var(--mv2-fz-sm);padding:7px 9px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);background:#fff;}
        .lc-table input:focus,.lc-table select:focus{outline:none;border-color:var(--mv2-primary-500);box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        .lc-code{color:var(--mv2-text-faint);font-size:var(--mv2-fz-xs);}
        .lc-btn{font-family:inherit;font-size:var(--mv2-fz-xs);font-weight:700;border:1px solid var(--mv2-border-default);background:#fff;border-radius:var(--mv2-r-sm);padding:7px 14px;cursor:pointer;white-space:nowrap;}
        .lc-btn:hover{border-color:var(--mv2-text-strong);}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/life-checklist/items') }}';

        document.querySelector('[data-tabpane="items"]').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-save]');
            if (!btn) return;

            var tr = btn.closest('tr[data-id]');
            var payload = {
                label: tr.querySelector('[data-field="label"]').value.trim(),
                hint: tr.querySelector('[data-field="hint"]').value.trim(),
                active: tr.querySelector('[data-field="active"]').value === '1',
            };
            if (!payload.label) { ndnToast('문구를 입력하세요.', { type: 'error' }); return; }

            btn.disabled = true;
            fetch(BASE + '/' + tr.getAttribute('data-id'), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(payload),
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    btn.disabled = false;
                    if (res.ok && res.j.ok) ndnToast('저장했습니다.', { type: 'success' });
                    else ndnToast(res.j.message || '저장하지 못했습니다.', { type: 'error' });
                })
                .catch(function () { btn.disabled = false; ndnToast('저장하지 못했습니다.', { type: 'error' }); });
        });
    })();
</script>
@endsection

@section('wwgrid')
<script>
    wwConsole({
        el: 'grid-lifechecklist',
        editable: false,
        title: '생활체크리스트',
        data: @json($rows),
        columns: [
            { header: '근로자', name: 'worker', width: 130, sortable: true },
            { header: '국적', name: 'nationality', width: 90, align: 'center', sortable: true },
            { header: '시·군', name: 'city', width: 110, sortable: true },
            { header: '농가', name: 'farm', width: 140, sortable: true },
            { header: '확인', name: 'done', width: 70, align: 'center', sortable: true },
            { header: '전체', name: 'total', width: 70, align: 'center' },
            { header: '진행률(%)', name: 'progress', width: 100, align: 'center', sortable: true },
            { header: '상태', name: 'state', width: 90, align: 'center', sortable: true },
            { header: '마지막 확인', name: 'last_checked', width: 140, align: 'center', sortable: true },
        ],
        onRowDblClick: function (row) {
            var pending = (row.pending && row.pending.length)
                ? row.pending.map(function (p) { return '· ' + p; }).join('\n')
                : '남은 항목이 없습니다.';
            ndnDetailModal({
                title: row.worker,
                subtitle: row.city + ' · ' + row.farm + ' · ' + row.done + '/' + row.total + ' 확인',
                rows: [
                    ['근로자', row.worker], ['국적', row.nationality], ['소속', row.city + ' · ' + row.farm],
                    ['진행', row.done + ' / ' + row.total + ' (' + row.progress + '%)'],
                    ['상태', row.state], ['마지막 확인', row.last_checked],
                    ['아직 확인하지 않은 항목', pending],
                ],
            });
        },
    });
</script>
@endsection
