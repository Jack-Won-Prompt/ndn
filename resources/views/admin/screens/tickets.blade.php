@extends('admin.screens.layout')
@section('title', '민원')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">민원</h1>
            <p class="screen__sub">근로자 발신 민원 · <strong>상태 셀 편집 후 [변경 저장]</strong> · 행 더블클릭으로 상세</p>
        </div>
    </div>

    <div id="grid-tickets"></div>
@endsection

@section('wwgrid')
<script>
    wwConsole({
        el: 'grid-tickets',
        editable: true,
        canAdd: false,
        canDelete: false,
        title: '민원',
        saveUrl: '{{ route('admin.grid.tickets.save') }}',
        data: @json($rows),
        columns: [
            { header: '번호', name: 'id', width: 70, align: 'center', sortable: true },
            { header: '근로자', name: 'worker', width: 140, sortable: true },
            { header: '유형', name: 'type', width: 110, align: 'center' },
            { header: '제목', name: 'subject', width: 320 },
            { header: '상태', name: 'status', width: 120, align: 'center', editor: 'combo',
              options: [{value:'open',label:'접수'},{value:'in_progress',label:'처리 중'},{value:'resolved',label:'완료'}] },
            { header: '접수일시', name: 'created', width: 150, align: 'center', sortable: true },
        ],
        onRowDblClick: function (row) {
            var LABEL = { open:'접수', in_progress:'처리 중', resolved:'완료' };
            ndnDetailModal({
                title: '민원 #' + row.id,
                subtitle: row.worker,
                rows: [
                    ['근로자', row.worker], ['유형', row.type], ['제목', row.subject],
                    ['내용', row.body], ['상태', LABEL[row.status] || row.status], ['접수일시', row.created],
                ],
            });
        },
    });
</script>
@endsection
