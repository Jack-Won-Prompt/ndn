@extends('admin.screens.layout')
@section('title', '후보자·평가')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">후보자·평가</h1>
            <p class="screen__sub">모집 명단 등록·수정 · <strong>편집 후 [변경 저장]</strong> · 엑셀 업로드/다운로드</p>
        </div>
    </div>

    <div id="grid-candidates"></div>
@endsection

@section('wwgrid')
<script>
    wwConsole({
        el: 'grid-candidates',
        editable: true,
        title: '후보자',
        saveUrl: '{{ route('admin.grid.candidates.save') }}',
        importUrl: '{{ route('admin.grid.candidates.import') }}',
        newRow: { nationality: 'BD', gender: 'male', status: 'applied' },
        data: @json($rows),
        columns: [
            { header: '이름', name: 'name', width: 160, editor: 'text', sortable: true },
            { header: '국적', name: 'nationality', width: 100, editor: 'combo', align: 'center',
              options: [{value:'BD',label:'방글라'},{value:'LA',label:'라오스'},{value:'LK',label:'스리랑카'},{value:'VN',label:'베트남'}] },
            { header: '나이', name: 'age', width: 80, editor: 'number', min: 18, max: 70 },
            { header: '성별', name: 'gender', width: 90, editor: 'combo', align: 'center',
              options: [{value:'male',label:'남성'},{value:'female',label:'여성'}] },
            { header: '상태', name: 'status', width: 110, editor: 'combo', align: 'center',
              options: [{value:'applied',label:'지원'},{value:'passed',label:'합격'},{value:'held',label:'보류'},{value:'rejected',label:'불합격'}] },
            { header: '대기 순번', name: 'queue_position', width: 100, editor: 'number', min: 1 },
        ],
    });
</script>
@endsection
