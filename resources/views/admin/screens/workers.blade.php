@extends('admin.screens.layout')
@section('title', '근로자')

@php
    $data = $rows->map(fn ($w) => [
        'id'          => $w->id,
        'name'        => $w->name,
        'nationality' => $w->nationality,
        'locale'      => $w->locale,
        'status'      => $w->status.'|'.($w->status === 'active' ? 'ok' : ''),
        'created'     => $w->created_at?->format('Y-m-d'),
    ])->values();
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">근로자</h1>
            <p class="screen__sub">행을 더블클릭하면 상세를 새 탭으로 엽니다 · 열 머리글로 정렬, 경계 드래그로 너비 조절</p>
        </div>
        <form class="toolbar" method="GET" action="{{ url('admin/screen/workers') }}">
            <input type="text" name="q" value="{{ $q }}" placeholder="이름 검색">
            <button type="submit">검색</button>
        </form>
    </div>

    <div id="grid-workers"></div>
@endsection

@section('grid')
<script>
    var gridWorkers = ndnGrid({
        el: 'grid-workers',
        frozenCount: 2,
        perPage: 20,
        data: @json($data),
        columns: [
            { name: 'id', header: '번호', width: 80, align: 'center', sortable: true },
            { name: 'name', header: '이름', minWidth: 180, sortable: true },
            { name: 'nationality', header: '국적', width: 90, align: 'center', sortable: true },
            { name: 'locale', header: '언어', width: 90, align: 'center' },
            { name: 'status', header: '상태', width: 120, align: 'center', renderer: { type: window.NDN_PillRenderer } },
            { name: 'created', header: '등록일', width: 130, align: 'center', sortable: true },
        ],
    });
    // 더블클릭 → 상세 탭
    gridWorkers.on('dblclick', function (ev) {
        if (ev.rowKey == null) return;
        var row = gridWorkers.getRow(ev.rowKey);
        if (row) ndnOpenTab('workers/' + row.id, '근로자 #' + row.id);
    });
</script>
@endsection
