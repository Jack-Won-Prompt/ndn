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
        // 더블클릭 → 제출물 상세(본인 기입 payload + 전자서명) 팝업
        // 서버 조회로 payload 복호화 + 개인정보 열람 감사 로그(§7-6)
        onRowDblClick: function (row) {
            var LABELS = { address_kr: '국내 주소', emergency_contact: '비상 연락처' };
            fetch('{{ url('admin/onboarding') }}/' + row.id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (o) {
                    var rows = [
                        ['근로자', o.worker],
                        ['상태', o.status],
                        ['제출일시', o.submitted_at],
                    ];
                    var p = o.payload || {};
                    Object.keys(p).forEach(function (k) {
                        var v = p[k];
                        rows.push([LABELS[k] || k, (v && typeof v === 'object') ? JSON.stringify(v) : v]);
                    });
                    rows.push(['전자서명', o.has_signature ? '첨부됨' : '없음']);
                    if (o.review_note) rows.push(['검수 메모', o.review_note]);

                    ndnDetailModal({
                        title: '온보딩 #' + o.id,
                        subtitle: o.worker,
                        rows: rows,
                        links: o.signature_url ? [{ label: '전자서명 파일 열기', href: o.signature_url }] : [],
                        note: '본인 기입 정보 열람은 감사 로그에 기록됩니다.',
                    });
                })
                .catch(function () { ndnToast('상세를 불러오지 못했습니다.', { type: 'error' }); });
        },
    });
</script>
@endsection
