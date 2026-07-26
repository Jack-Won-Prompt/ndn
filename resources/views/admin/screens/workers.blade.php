@extends('admin.screens.layout')
@section('title', '근로자')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">근로자</h1>
            <p class="screen__sub">비민감 정보만 편집 · <strong>편집 후 [변경 저장]</strong> · 여권번호·생년월일·전화는 §7에 따라 목록에 표시하지 않음(엑셀 업로드로만 등록)</p>
        </div>
    </div>

    <div id="grid-workers"></div>
@endsection

@section('wwgrid')
<script>
    wwConsole({
        el: 'grid-workers',
        editable: true,
        title: '근로자',
        saveUrl: '{{ route('admin.grid.workers.save') }}',
        importUrl: '{{ route('admin.grid.workers.import') }}',
        newRow: { nationality: 'BD', locale: 'bn', status: 'active' },
        data: @json($rows),
        columns: [
            { header: '이름', name: 'name', width: 160, editor: 'text', sortable: true },
            { header: '국적', name: 'nationality', width: 100, editor: 'combo', align: 'center',
              options: [{value:'BD',label:'방글라'},{value:'LA',label:'라오스'},{value:'LK',label:'스리랑카'},{value:'VN',label:'베트남'}] },
            { header: '언어', name: 'locale', width: 100, editor: 'combo', align: 'center',
              options: [{value:'ko',label:'한국어'},{value:'bn',label:'벵골어'},{value:'lo',label:'라오어'},{value:'si',label:'싱할라어'},{value:'vi',label:'베트남어'}] },
            { header: '상태', name: 'status', width: 110, editor: 'combo', align: 'center',
              options: [{value:'pending',label:'승인 대기'},{value:'active',label:'재직'},{value:'inactive',label:'비활성'},{value:'returned',label:'귀국'},{value:'rejected',label:'가입 거절'}] },
        ],
    });
</script>
@endsection
