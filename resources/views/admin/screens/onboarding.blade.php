@extends('admin.screens.layout')
@section('title', '온보딩 검수')

@php
    $kind = fn (string $s) => match ($s) {
        'submitted' => 'warn', 'under_review' => 'info', 'approved' => 'ok', 'rejected' => 'err', default => '',
    };
    $data = $rows->map(fn ($o) => [
        'id'        => $o->id,
        'worker'    => $o->worker?->name ?? '—',
        'status'    => $o->status->label().'|'.$kind($o->status->value),
        'submitted' => $o->submitted_at?->format('Y-m-d H:i') ?? '—',
        'note'      => $o->review_note ?? '—',
    ])->values();
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">온보딩 검수</h1>
            <p class="screen__sub">행을 더블클릭하면 상세를 팝업으로 확인 · 본인 기입 정보는 암호화 저장</p>
        </div>
    </div>

    <div id="grid-onboarding"></div>
@endsection

@section('grid')
<script>
    ndnGrid({
        el: 'grid-onboarding',
        frozenCount: 1,
        perPage: 20,
        data: @json($data),
        columns: [
            { name: 'id', header: '번호', width: 70, align: 'center', sortable: true },
            { name: 'worker', header: '근로자', width: 160, sortable: true, filter: 'text' },
            { name: 'status', header: '상태', width: 130, align: 'center', renderer: { type: window.NDN_PillRenderer } },
            { name: 'submitted', header: '제출일시', width: 160, align: 'center', sortable: true },
            { name: 'note', header: '검수 메모', minWidth: 200 },
        ],
        onRowDblClick: function (row) {
            ndnDetailModal({
                title: '온보딩 #' + row.id,
                subtitle: row.worker,
                rows: [
                    ['근로자', row.worker],
                    ['상태', row.status, true],
                    ['제출일시', row.submitted],
                    ['검수 메모', row.note],
                ],
                note: '본인 기입 정보(주소·비상연락처 등)는 암호화 저장되어 목록에 표시하지 않습니다.',
            });
        },
    });
</script>
@endsection
