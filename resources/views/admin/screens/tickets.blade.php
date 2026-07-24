@extends('admin.screens.layout')
@section('title', '민원')

@php
    $data = $rows->map(fn ($t) => [
        'id'      => $t->id,
        'worker'  => $t->worker?->name ?? '—',
        'type'    => $t->type->label(),
        'subject' => $t->subject,
        'status'  => $t->status->value,   // 편집 가능 셀: 원값(라벨은 에디터/포맷터가 처리)
        'created' => $t->created_at?->format('Y-m-d H:i'),
        'body'    => $t->body,            // 상세 팝업용(그리드 미표시)
    ])->values();
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">민원</h1>
            <p class="screen__sub">행을 더블클릭하면 상세를 팝업으로 확인 · <strong>상태 셀 더블클릭은 인라인 편집·저장</strong> · 열 머리글 드래그로 재정렬</p>
        </div>
    </div>

    <div id="grid-tickets"></div>
@endsection

@section('grid')
<script>
    var STATUS_LABEL = { open: '접수', in_progress: '처리 중', resolved: '완료' };

    var gridTickets = ndnGrid({
        el: 'grid-tickets',
        title: '민원',
        frozenCount: 1,
        perPage: 20,
        data: @json($data),
        columns: [
            { name: 'id', header: '번호', width: 70, align: 'center', sortable: true },
            { name: 'worker', header: '근로자', width: 140, sortable: true, filter: 'text' },
            { name: 'type', header: '유형', width: 110, align: 'center', filter: 'select' },
            { name: 'subject', header: '제목', minWidth: 220, filter: 'text' },
            {
                name: 'status', header: '상태 (편집)', width: 130, align: 'center',
                formatter: function (o) { return STATUS_LABEL[o.value] || o.value; },
                editor: {
                    type: 'select',
                    options: { listItems: [
                        { text: '접수', value: 'open' },
                        { text: '처리 중', value: 'in_progress' },
                        { text: '완료', value: 'resolved' },
                    ] },
                },
            },
            { name: 'created', header: '접수일시', width: 150, align: 'center', sortable: true },
            { name: 'body', header: '내용', hidden: true },
        ],
        // 행 더블클릭(상태 셀 제외) → 상세 팝업
        onRowDblClick: function (row) {
            ndnDetailModal({
                title: '민원 #' + row.id,
                subtitle: row.worker,
                rows: [
                    ['근로자', row.worker],
                    ['유형', row.type],
                    ['제목', row.subject],
                    ['내용', row.body],
                    ['상태', STATUS_LABEL[row.status] || row.status],
                    ['접수일시', row.created],
                ],
            });
        },
        // 셀 편집 저장: 상태 변경 시 서버에 반영
        onEdit: function (row, columnName, value, prevValue, grid) {
            if (columnName !== 'status' || value === prevValue) return;
            var token = document.querySelector('meta[name="csrf-token"]').content;
            fetch('{{ url('admin/tickets') }}/' + row.id + '/status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
                body: JSON.stringify({ status: value }),
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
              .then(function (res) {
                  if (res.ok) {
                      ndnToast('#' + row.id + ' 상태를 “' + (STATUS_LABEL[value] || value) + '”(으)로 변경했습니다.', { type: 'success' });
                  } else {
                      // 전이 불가 등 → 되돌림
                      grid.setValue(row.rowKey, 'status', prevValue);
                      ndnToast(res.j.message || '변경할 수 없습니다.', { type: 'error', title: '변경 실패' });
                  }
              })
              .catch(function () {
                  grid.setValue(row.rowKey, 'status', prevValue);
                  ndnToast('네트워크 오류로 변경하지 못했습니다.', { type: 'error', title: '변경 실패' });
              });
        },
    });
</script>
@endsection
