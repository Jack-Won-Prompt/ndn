@extends('admin.screens.layout')
@section('title', '수요 신청')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">수요 신청</h1>
            <p class="screen__sub">관리자가 시·농가를 대신해 등록·수정 · <strong>편집 후 [변경 저장]</strong> · 엑셀 업로드/다운로드</p>
        </div>
    </div>

    <div id="grid-demand"></div>
@endsection

@section('wwgrid')
@php
    // 국적 선택지는 Nationality enum 한 곳에서 온다. 화면마다 따로 적어 두면
    // 송출국이 늘 때 어딘가 하나가 빠진다(실제로 네팔·키르기스스탄이 빠져 있었다).
    $natOptions = collect(App\Domains\Recruitment\Enums\Nationality::adminOptions())
        ->map(fn ($label, $code) => ['value' => $code, 'label' => $label])->values();
@endphp
<script>
    var FARMS  = @json($farms);
    var CITIES = @json($cities);

    wwConsole({
        el: 'grid-demand',
        editable: true,
        title: '수요신청',
        saveUrl: '{{ route('admin.grid.demand.save') }}',
        importUrl: '{{ route('admin.grid.demand.import') }}',
        newRow: { nationality: 'BD', gender: 'any', headcount: 1, status: 'draft' },
        data: @json($rows),
        columns: [
            { header: '농가', name: 'farm_id', width: 150, editor: 'combo', options: FARMS, sortable: true },
            { header: '지자체', name: 'city_id', width: 130, editor: 'combo', options: CITIES },
            { header: '국적', name: 'nationality', width: 80, editor: 'combo', align: 'center',
              options: @json($natOptions) },
            { header: '인원', name: 'headcount', width: 80, editor: 'number', min: 1, max: 999 },
            { header: '성별', name: 'gender', width: 90, editor: 'combo', align: 'center',
              options: [{value:'male',label:'남성'},{value:'female',label:'여성'},{value:'any',label:'무관'}] },
            { header: '품목', name: 'crop', width: 110, editor: 'text' },
            { header: '시작일', name: 'period_start', width: 120, editor: 'date' },
            { header: '종료일', name: 'period_end', width: 120, editor: 'date' },
            { header: '상태', name: 'status', width: 120, editor: 'combo', align: 'center',
              options: [{value:'draft',label:'작성 중'},{value:'submitted',label:'제출'},{value:'aggregated',label:'취합'},{value:'letter_issued',label:'레터 발행'},{value:'rejected',label:'반려'}] },
            { header: '비고', name: 'note', width: 200, editor: 'text' },
        ],
    });
</script>
@endsection
