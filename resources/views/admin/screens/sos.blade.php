@extends('admin.screens.layout')
@section('title', '긴급 SOS')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">긴급 SOS</h1>
            <p class="screen__sub">근로자가 앱에서 누른 긴급 요청 · <strong>미확인이 위로, 오래 방치된 것부터</strong> · 좌표는 누른 그 순간 1회분입니다</p>
        </div>
    </div>

    @if ($openCount > 0)
        <div class="sos-alert">
            <b>미확인 {{ $openCount }}건</b> — 아직 아무도 확인하지 않은 긴급 요청이 있습니다.
        </div>
    @endif

    <div id="grid-sos"></div>

    <p class="sos-hint">
        처리할 건을 <strong>체크</strong>한 뒤 툴바의 <strong>[확인 처리]</strong> · <strong>[종료 처리]</strong>를 누르세요.
        <strong>[지도 ▸]</strong> 칸을 누르면 그 좌표가 새 창에 열립니다.
        <br>신고 내용(발신 시각·좌표·근로자)은 근로자가 보낸 것이라 <strong>고칠 수 없습니다</strong> —
        여기서 하는 일은 대응 상태를 남기는 것뿐입니다.
    </p>

    <style>
        .sos-hint{font-size:var(--mv2-fz-xs);color:var(--mv2-text-faint);margin:10px 2px 0;line-height:1.7;}
        .sos-alert{background:#FDECEC;border:1px solid #F5C2C0;color:#8A1F1C;border-radius:var(--mv2-r-lg);
            padding:13px 16px;margin-bottom:14px;font-size:var(--mv2-fz-sm);line-height:1.6;}
        .sos-alert b{font-weight:800;}
        .sos-dim{color:var(--mv2-text-faint);font-size:var(--mv2-fz-xs);}
        .sos-late{color:#B3261E;font-weight:800;}
    </style>
@endsection

@section('wwgrid')
<script>
    var SOS_BASE = '{{ url('admin/sos') }}';

    // 신고 내용은 근로자가 보낸 것이라 **읽기 전용**이다. 여기서 하는 일은
    // 대응 상태를 남기는 것뿐이라 [신규 행]·[변경 저장] 을 두지 않는다.
    var sosGrid = wwConsole({
        el: 'grid-sos',
        title: '긴급SOS',
        data: @json($rows, JSON_UNESCAPED_UNICODE),
        rowCheckbox: true,
        buttons: [
            { label: '확인 처리', primary: true, onClick: function (g) { sosBulk(g, 'acknowledged'); } },
            { label: '종료 처리', onClick: function (g) { sosBulk(g, 'closed'); } },
        ],
        columns: [
            { header: '상태', name: 'status_label', width: 84, align: 'center', sortable: true },
            { header: '근로자', name: 'worker', width: 150, sortable: true },
            { header: '국적', name: 'nationality', width: 66, align: 'center' },
            { header: '소속', name: 'belong', width: 200 },
            { header: '발신 시각', name: 'alerted_at', width: 150, align: 'center', sortable: true },
            { header: '경과·대응', name: 'elapsed', width: 100, align: 'center' },
            { header: '지연', name: 'late', width: 60, align: 'center' },
            { header: '좌표', name: 'coords', width: 165, align: 'center' },
            { header: '지도', name: 'map', width: 74, align: 'center' },
            { header: '확인자', name: 'acknowledged_by', width: 120 },
            { header: '확인 시각', name: 'acknowledged_at', width: 150, align: 'center' },
            { header: '메모', name: 'note', width: 220 },
        ],
    });

    // 좌표는 §7-2 가 허용한 두 자리 중 하나다 — 누른 그 순간 1회분. 새 창으로만 연다.
    document.getElementById('grid-sos').addEventListener('click', function (e) {
        var cell = e.target.closest('[data-col-name="map"][data-row-index]');
        if (!cell) return;
        var row = sosGrid.getData()[parseInt(cell.getAttribute('data-row-index'), 10)];
        if (row && row.map_url) window.open(row.map_url, '_blank', 'noopener');
    });

    function sosBulk(grid, status) {
        var picked = grid.getCheckedRows();
        if (!picked.length) { ndnToast('처리할 건을 체크하세요.', { type: 'info' }); return; }

        // 확인은 미확인만, 종료는 확인된 것만 넘어갈 수 있다.
        var rows = picked.filter(function (r) {
            return status === 'acknowledged' ? r.status === 'open' : r.status === 'acknowledged';
        });
        var label = status === 'acknowledged' ? '확인 처리' : '종료 처리';

        if (!rows.length) {
            ndnToast('체크한 ' + picked.length + '건은 지금 ' + label + '할 수 있는 상태가 아닙니다.', { type: 'info' });
            return;
        }

        var skipped = picked.length - rows.length;
        var tail = skipped ? ' (상태가 맞지 않는 ' + skipped + '건은 건너뜁니다)' : '';

        ndnConfirm(rows.length + '건을 ' + label + '합니다' + tail
            + (status === 'acknowledged' ? '. 확인한 사람과 시각이 기록됩니다.' : '.'),
            { title: label, okText: label })
            .then(function (ok) {
                if (!ok) return;
                fetch(SOS_BASE + '/status-bulk', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json', 'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').content,
                    },
                    body: JSON.stringify({ status: status, ids: rows.map(function (r) { return r.id; }) }),
                })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (res) {
                        if (!res.ok) { ndnToast(res.j.message || '처리하지 못했습니다.', { type: 'error' }); return; }
                        grid.setData(res.j.rows);
                        ndnToast(res.j.message, { type: 'success' });
                    })
                    .catch(function () { ndnToast('처리하지 못했습니다.', { type: 'error' }); });
            });
    }
</script>
@endsection
