@extends('admin.screens.layout')
@section('title', '후보자·평가')

@php
    $kind = fn (string $s) => match ($s) {
        'passed' => 'ok', 'held' => 'warn', 'rejected' => 'err', default => '',
    };
    $data = $rows->map(fn ($c) => [
        'id'          => $c->id,
        'name'        => $c->name,
        'nationality' => $c->nationality,
        'age'         => $c->age ?? '—',
        'status'      => $c->status->label().'|'.$kind($c->status->value),
        'queue'       => $c->queue_position ?? '—',
    ])->values();
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">후보자·평가</h1>
            <p class="screen__sub">모집 명단 및 면접 결과 (합격/보류/불합격 · 보류자 대기열 순번)</p>
        </div>
    </div>

    <div id="grid-candidates"></div>
@endsection

@section('grid')
<script>
    ndnGrid({
        el: 'grid-candidates',
        frozenCount: 2,
        perPage: 20,
        data: @json($data),
        columns: [
            { name: 'id', header: '번호', width: 70, align: 'center', sortable: true },
            { name: 'name', header: '이름', minWidth: 160, sortable: true, filter: 'text' },
            { name: 'nationality', header: '국적', width: 80, align: 'center', sortable: true, filter: 'select' },
            { name: 'age', header: '나이', width: 70, align: 'center', sortable: true },
            { name: 'status', header: '상태', width: 110, align: 'center', renderer: { type: window.NDN_PillRenderer } },
            { name: 'queue', header: '대기 순번', width: 110, align: 'center', sortable: true },
        ],
    });
</script>
@endsection
