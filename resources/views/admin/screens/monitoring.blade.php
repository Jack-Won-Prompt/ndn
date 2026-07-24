@extends('admin.screens.layout')
@section('title', '월별 점검')

@php
    $ox = fn (bool $b) => $b ? '양호|ok' : '이상|err';
    $rk = fn (string $s) => match ($s) { 'high' => 'high|err', 'medium' => '주의|warn', default => '낮음|ok' };
    $data = $rows->map(fn ($iv) => [
        'id'         => $iv->id,
        'worker'     => $iv->worker?->name ?? '—',
        'date'       => $iv->interviewed_on?->format('Y-m-d'),
        'pay'        => $ox($iv->pay_received),
        'discrim'    => $ox($iv->no_discrimination),
        'rules'      => $ox($iv->follows_rules),
        'group'      => $ox($iv->adapts_group),
        'health'     => $ox($iv->health_ok),
        'flight'     => $ox($iv->no_flight_signs),
        'risk'       => $iv->risk_level->value === 'high' ? '고위험|err'
                        : ($iv->risk_level->value === 'medium' ? '주의|warn' : '낮음|ok'),
        'memo'       => $iv->memo,
    ])->values();
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">월별 점검</h1>
            <p class="screen__sub">행을 더블클릭하면 상세를 팝업으로 확인 · 6개 항목 · 이탈 리스크(행동 신호 기반, 위치 추적 미사용)</p>
        </div>
    </div>

    <div id="grid-monitoring"></div>
@endsection

@section('grid')
<script>
    var P = { type: window.NDN_PillRenderer };
    ndnGrid({
        el: 'grid-monitoring',
        frozenCount: 2,
        perPage: 20,
        data: @json($data),
        columns: [
            { name: 'id', header: '번호', width: 60, align: 'center', sortable: true },
            { name: 'worker', header: '근로자', width: 130, sortable: true, filter: 'text' },
            { name: 'date', header: '점검일', width: 110, align: 'center', sortable: true },
            { name: 'pay', header: '급여', width: 84, align: 'center', renderer: P },
            { name: 'discrim', header: '차별없음', width: 90, align: 'center', renderer: P },
            { name: 'rules', header: '규칙', width: 84, align: 'center', renderer: P },
            { name: 'group', header: '단체생활', width: 90, align: 'center', renderer: P },
            { name: 'health', header: '건강', width: 84, align: 'center', renderer: P },
            { name: 'flight', header: '이탈징후', width: 90, align: 'center', renderer: P },
            { name: 'risk', header: '리스크', width: 96, align: 'center', renderer: P, sortable: true },
            { name: 'memo', header: '메모', hidden: true },
        ],
        onRowDblClick: function (row) {
            ndnDetailModal({
                title: '월별 점검 #' + row.id,
                subtitle: row.worker + ' · ' + row.date,
                rows: [
                    ['근로자', row.worker],
                    ['점검일', row.date],
                    ['급여 수령', row.pay, true],
                    ['차별 없음', row.discrim, true],
                    ['생활 규칙', row.rules, true],
                    ['단체 생활', row.group, true],
                    ['건강', row.health, true],
                    ['이탈 징후', row.flight, true],
                    ['이탈 리스크', row.risk, true],
                    ['메모', row.memo],
                ],
            });
        },
    });
</script>
@endsection
