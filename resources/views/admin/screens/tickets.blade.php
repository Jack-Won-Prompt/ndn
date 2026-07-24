@extends('admin.screens.layout')
@section('title', '민원')

@php
    $kind = fn (string $s) => match ($s) {
        'open' => 'warn', 'in_progress' => 'info', 'resolved' => 'ok', default => '',
    };
    $data = $rows->map(fn ($t) => [
        'id'      => $t->id,
        'worker'  => $t->worker?->name ?? '—',
        'type'    => $t->type->label(),
        'subject' => $t->subject,
        'status'  => $t->status->label().'|'.$kind($t->status->value),
        'created' => $t->created_at?->format('Y-m-d H:i'),
    ])->values();
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">민원</h1>
            <p class="screen__sub">근로자 발신 민원 (문제신고/문의/연장/조기귀국)</p>
        </div>
    </div>

    <div id="grid-tickets"></div>
@endsection

@section('grid')
<script>
    ndnGrid({
        el: 'grid-tickets',
        frozenCount: 1,
        perPage: 20,
        data: @json($data),
        columns: [
            { name: 'id', header: '번호', width: 70, align: 'center', sortable: true },
            { name: 'worker', header: '근로자', width: 140, sortable: true },
            { name: 'type', header: '유형', width: 110, align: 'center' },
            { name: 'subject', header: '제목', minWidth: 220 },
            { name: 'status', header: '상태', width: 110, align: 'center', renderer: { type: window.NDN_PillRenderer } },
            { name: 'created', header: '접수일시', width: 150, align: 'center', sortable: true },
        ],
    });
</script>
@endsection
