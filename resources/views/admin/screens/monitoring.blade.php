@extends('admin.screens.layout')
@section('title', '월별 점검')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">월별 점검</h1>
            <p class="screen__sub">행 더블클릭으로 상세 · 6개 항목 · 이탈 리스크(행동 신호 기반, 위치 추적 미사용)</p>
        </div>
    </div>

    <div id="grid-monitoring"></div>
@endsection

@section('wwgrid')
<script>
    wwConsole({
        el: 'grid-monitoring',
        editable: false,
        title: '월별점검',
        data: @json($rows),
        columns: [
            { header: '번호', name: 'id', width: 60, align: 'center', sortable: true },
            { header: '근로자', name: 'worker', width: 120, sortable: true },
            { header: '시·군', name: 'city', width: 110, sortable: true },
            { header: '농가', name: 'farm', width: 140, sortable: true },
            { header: '점검일', name: 'date', width: 100, align: 'center', sortable: true },
            { header: '급여', name: 'pay', width: 80, align: 'center' },
            { header: '차별없음', name: 'discrim', width: 90, align: 'center' },
            { header: '규칙', name: 'rules', width: 80, align: 'center' },
            { header: '단체생활', name: 'group', width: 90, align: 'center' },
            { header: '건강', name: 'health', width: 80, align: 'center' },
            { header: '이탈징후', name: 'flight', width: 90, align: 'center' },
            { header: '리스크', name: 'risk', width: 90, align: 'center', sortable: true },
        ],
        onRowDblClick: function (row) {
            ndnDetailModal({
                title: '월별 점검 #' + row.id,
                subtitle: row.worker + ' · ' + row.date,
                rows: [
                    ['근로자', row.worker], ['소속', row.city + ' · ' + row.farm], ['점검일', row.date],
                    ['급여 수령', row.pay], ['차별 없음', row.discrim], ['생활 규칙', row.rules],
                    ['단체 생활', row.group], ['건강', row.health], ['이탈 징후', row.flight],
                    ['이탈 리스크', row.risk], ['메모', row.memo],
                ],
            });
        },
    });
</script>
@endsection
