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
            <p class="screen__sub">셀프 온보딩 제출물 (본인 기입 정보는 암호화 저장)</p>
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
            { name: 'worker', header: '근로자', width: 160, sortable: true },
            { name: 'status', header: '상태', width: 130, align: 'center', renderer: { type: window.NDN_PillRenderer } },
            { name: 'submitted', header: '제출일시', width: 160, align: 'center', sortable: true },
            { name: 'note', header: '검수 메모', minWidth: 200 },
        ],
    });
</script>
@endsection
