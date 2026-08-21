@extends('admin.screens.layout')
@section('title', '계정 삭제 요청')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">계정 삭제 요청</h1>
            <p class="screen__sub">
                공개 페이지에서 접수된 삭제 요청 · <strong>상태를 고른 뒤 [변경 저장]</strong> ·
                완료 처리 후 <strong>근로자 화면에서 해당 계정을 비활성/삭제</strong>하면 90일 후 민감정보가 자동 파기됩니다(§7-7)
            </p>
        </div>
    </div>

    <div id="grid-account-deletions"></div>

    <p class="ad-hint">
        요청을 <strong>새로 만들 수는 없습니다</strong> — 본인이 공개 페이지에서 접수하는 것이라,
        관리자가 대신 만들면 &lsquo;본인이 요청했다&rsquo;는 증빙이 사라집니다.
        접수 기록 자체를 치우려면 행을 체크하고 <strong>[행 삭제]</strong>를 누르세요 — 확인하면 그 자리에서 지워집니다.
    </p>

    <style>
        .ad-hint { font-size: var(--mv2-fz-xs); color: var(--mv2-text-faint); margin: 10px 2px 0; line-height: 1.7; }
    </style>
@endsection

@section('wwgrid')
<script>
    wwConsole({
        el: 'grid-account-deletions',
        editable: true,
        title: '계정삭제요청',
        saveUrl: '{{ route('admin.account-deletions.save') }}',
        // 접수는 본인이 하는 것이라 여기서 새로 만들지 않는다.
        canAdd: false,
        deleteWarning: '접수 기록이 사라집니다. 계정 자체는 [근로자] 화면에서 따로 처리해야 합니다.',
        data: @json($rows, JSON_UNESCAPED_UNICODE),
        columns: [
            { header: '번호', name: 'id', width: 60, align: 'center', sortable: true },
            { header: '이름', name: 'name', width: 130, sortable: true },
            { header: '이메일(로그인 ID)', name: 'email', width: 220, sortable: true },
            { header: '사유', name: 'reason', width: 260 },
            { header: '신청일시', name: 'requested_at', width: 150, align: 'center', sortable: true },
            // 이 화면에서 하는 일은 상태를 정하고 메모를 남기는 것뿐이다.
            { header: '상태', name: 'status', width: 100, editor: 'combo', align: 'center',
              options: @json(App\Http\Controllers\Admin\AccountDeletionAdminController::statusOptions()) },
            { header: '처리일시', name: 'processed', width: 150, align: 'center' },
            { header: '처리 메모', name: 'admin_note', width: 240, editor: 'text' },
        ],
    });
</script>
@endsection
