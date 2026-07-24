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
    ])->values();
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">수요 신청</h1>
            <p class="screen__sub">농가 수요 신청 현황 (모니터링 · 상태 전이는 도메인 Action)</p>
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
        ],
    });
</script>
@endsection
