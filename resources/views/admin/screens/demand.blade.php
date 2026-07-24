@extends('admin.screens.layout')
@section('title', '수요 신청')

@php
    $kind = fn (string $s) => match ($s) {
        'submitted' => 'warn', 'aggregated' => 'info', 'letter_issued' => 'ok', 'rejected' => 'err', default => '',
    };
    $data = $rows->map(fn ($d) => [
        'id'          => $d->id,
        'farm'        => $d->farm?->name ?? '—',
        'nationality' => $d->nationality,
        'headcount'   => $d->headcount,
        'crop'        => $d->crop,
        'status'      => $d->status->label().'|'.$kind($d->status->value),
        'period'      => $d->period_start?->format('Y-m-d'),
        'created'     => $d->created_at?->format('Y-m-d'),
        'period_end'  => $d->period_end?->format('Y-m-d'),
        'note'        => $d->note,
    ])->values();
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">수요 신청</h1>
            <p class="screen__sub">행을 더블클릭하면 상세를 팝업으로 확인 · 상태 전이는 도메인 Action</p>
        </div>
    </div>

    <div id="grid-demand"></div>
@endsection

@section('grid')
<script>
    ndnGrid({
        el: 'grid-demand',
        frozenCount: 2,
        perPage: 20,
        data: @json($data),
        columns: [
            { name: 'id', header: '번호', width: 70, align: 'center', sortable: true },
            { name: 'farm', header: '농가', minWidth: 180, sortable: true, filter: 'text' },
            { name: 'nationality', header: '국적', width: 80, align: 'center', sortable: true, filter: 'select' },
            { name: 'headcount', header: '인원', width: 80, align: 'right', sortable: true },
            { name: 'crop', header: '품목', minWidth: 120, filter: 'select' },
            { name: 'status', header: '상태', width: 120, align: 'center', renderer: { type: window.NDN_PillRenderer } },
            { name: 'period', header: '시작일', width: 120, align: 'center', sortable: true },
            { name: 'created', header: '등록일', width: 120, align: 'center', sortable: true },
            { name: 'period_end', header: '종료일', hidden: true },
            { name: 'note', header: '비고', hidden: true },
        ],
        onRowDblClick: function (row) {
            ndnDetailModal({
                title: '수요 신청 #' + row.id,
                subtitle: row.farm,
                rows: [
                    ['농가', row.farm],
                    ['국적', row.nationality],
                    ['인원', row.headcount + '명'],
                    ['품목', row.crop],
                    ['상태', row.status, true],
                    ['근무 기간', row.period + ' ~ ' + (row.period_end || '—')],
                    ['비고', row.note],
                    ['등록일', row.created],
                ],
            });
        },
    });
</script>
@endsection
