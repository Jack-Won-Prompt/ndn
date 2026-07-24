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
            <p class="screen__sub">행을 더블클릭하면 상세를 팝업으로 확인합니다 · 열 머리글로 정렬, 경계 드래그로 너비 조절</p>
        </div>
    </div>

    <div id="grid-workers"></div>
@endsection

@section('grid')
<script>
    ndnGrid({
        el: 'grid-workers',
        frozenCount: 2,
        perPage: 20,
        data: @json($data),
        columns: [
            { name: 'id', header: '번호', width: 80, align: 'center', sortable: true },
            { name: 'name', header: '이름', minWidth: 180, sortable: true, filter: 'text' },
            { name: 'nationality', header: '국적', width: 90, align: 'center', sortable: true, filter: 'select' },
            { name: 'locale', header: '언어', width: 90, align: 'center', filter: 'select' },
            { name: 'status', header: '상태', width: 120, align: 'center', renderer: { type: window.NDN_PillRenderer } },
            { name: 'created', header: '등록일', width: 130, align: 'center', sortable: true },
        ],
        // 더블클릭 → 상세 팝업 (서버 조회로 감사 로그 기록 · 민감 필드 제외 §7-6)
        onRowDblClick: function (row) {
            fetch('{{ url('admin/screen/workers') }}/' + row.id + '?format=json', {
                headers: { 'Accept': 'application/json' },
            })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (w) {
                    ndnDetailModal({
                        title: '근로자 #' + w.id,
                        subtitle: w.name,
                        rows: [
                            ['이름', w.name],
                            ['국적', w.nationality],
                            ['언어', w.locale],
                            ['상태', (w.status === 'active' ? '재직|ok' : w.status), true],
                            ['등록일', w.created],
                        ],
                        note: '여권번호·생년월일·전화번호 등 민감 정보는 표시하지 않습니다. 이 열람은 감사 로그에 기록됩니다.',
                    });
                })
                .catch(function () { ndnToast('상세를 불러오지 못했습니다.', { type: 'error' }); });
        },
    });
</script>
@endsection
