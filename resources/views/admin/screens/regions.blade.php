@extends('admin.screens.layout')
@section('title', '지역별 모집·배치')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">지역별 모집·배치</h1>
            <p class="screen__sub">
                시군별로 모집 정원과 배치 현황을 나눠 봅니다 · <strong>[농가별 ▸]</strong> 칸을 누르면 해당 지역의 농가별 배치 인원이 열립니다 ·
                정원·모집 여부는 <strong>농가·지자체</strong> 화면에서 수정합니다
            </p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">지역 현황</button>
        <button type="button" class="screen-tab" data-tab="detail" id="rg-detail-tab" hidden>농가별</button>
    </div>

    <div data-tabpane="list">
        <div id="grid-regions"></div>
    </div>

    <div data-tabpane="detail" hidden>
        <div class="dtl-head">
            <b id="rg-title">농가별 배치</b>
            <div class="dtl-head__actions">
                <button type="button" class="dtl-back" onclick="window.ndnSwitchTab('list')">← 지역 현황</button>
            </div>
        </div>
        <dl class="dtl-dl" id="rg-summary"></dl>
        <div id="grid-region-farms"></div>
    </div>

    <style>
        .dtl-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
        .dtl-back { font-family: inherit; font-size: var(--mv2-fz-xs); font-weight: 700; background: #fff;
            border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-sm); padding: 6px 13px; cursor: pointer; }
        .dtl-dl { display: grid; grid-template-columns: 110px 1fr; gap: 4px 12px; margin: 0 0 14px;
            font-size: var(--mv2-fz-sm); color: var(--mv2-text-strong); }
        .dtl-dl dt { color: var(--mv2-text-muted); font-weight: 700; }
        .dtl-dl dd { margin: 0; }
    </style>
@endsection

@section('wwgrid')
<script>
    // 지역 현황은 **읽기 전용**이다. 정원·모집 여부는 기준정보(농가·지자체)가 원본이고,
    // 여기서 고칠 수 있게 하면 같은 값을 두 곳에서 고치게 된다.
    var rgGrid = wwConsole({
        el: 'grid-regions',
        title: '지역별모집배치',
        data: @json($rows, JSON_UNESCAPED_UNICODE),
        columns: [
            { header: '지역', name: 'name', width: 130, sortable: true },
            { header: '광역/도', name: 'region', width: 110, sortable: true },
            { header: '모집', name: 'open_label', width: 90, align: 'center', sortable: true },
            { header: '정원', name: 'quota_label', width: 90, align: 'center' },
            { header: '지원자', name: 'applicants', width: 80, align: 'center', sortable: true },
            { header: '승인대기', name: 'pending', width: 84, align: 'center' },
            { header: '잔여', name: 'remaining_label', width: 70, align: 'center' },
            { header: '배치 인원', name: 'placed', width: 90, align: 'center', sortable: true },
            { header: '농가', name: 'farms', width: 70, align: 'center', sortable: true },
            { header: '농가별', name: 'pick', width: 96, align: 'center' },
        ],
    });

    // 농가별 표는 지역을 고르기 전에는 채울 것이 없다. 처음 열 때 만든다.
    var rgFarmGrid = null;

    document.getElementById('grid-regions').addEventListener('click', function (e) {
        var cell = e.target.closest('[data-col-name="pick"][data-row-index]');
        if (!cell) return;

        var row = rgGrid.getData()[parseInt(cell.getAttribute('data-row-index'), 10)];
        if (!row || !row.id) return;

        fetch('{{ url('admin/regions') }}/' + row.id, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (d) {
                document.getElementById('rg-title').textContent = d.label + ' · 농가별 배치';
                document.getElementById('rg-summary').innerHTML =
                    '<dt>모집 정원</dt><dd>' + (d.quota == null ? '제한 없음' : d.quota + '명') + '</dd>'
                    + '<dt>지원자</dt><dd>' + d.applicants + '명</dd>'
                    + '<dt>모집 상태</dt><dd>' + (d.recruiting ? '모집 중' : '중지') + '</dd>';

                document.getElementById('rg-detail-tab').hidden = false;
                window.ndnSwitchTab('detail');

                // 탭이 보이게 된 다음에 만든다 — 숨은 채로 만들면 폭이 0 으로 잡힌다.
                setTimeout(function () {
                    if (!rgFarmGrid) {
                        rgFarmGrid = wwConsole({
                            el: 'grid-region-farms',
                            title: '지역농가배치',
                            height: 420,
                            data: d.farms,
                            columns: [
                                { header: '농가', name: 'name', width: 200, sortable: true },
                                { header: '품목', name: 'main_crop', width: 120, align: 'center' },
                                { header: '주소', name: 'address', width: 320 },
                                { header: '배치 인원', name: 'placed', width: 100, align: 'center', sortable: true },
                            ],
                        });
                        return;
                    }
                    rgFarmGrid.setData(d.farms);
                }, 0);
            })
            .catch(function () { ndnToast('지역 상세를 불러오지 못했습니다.', { type: 'error' }); });
    });
</script>
@endsection
