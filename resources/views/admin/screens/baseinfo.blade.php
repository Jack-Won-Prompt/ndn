@extends('admin.screens.layout')
@section('title', '농가·지자체')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">농가·지자체 기준정보</h1>
            <p class="screen__sub">수요 신청·배치의 기준이 되는 지자체·농가 정보 등록·수정 · <strong>각 표 편집 후 [변경 저장]</strong></p>
        </div>
    </div>

    <h2 style="font-size:15px;font-weight:700;color:var(--mv2-text-strong);margin:6px 0 8px">지자체</h2>
    <div id="grid-cities"></div>

    <h2 style="font-size:15px;font-weight:700;color:var(--mv2-text-strong);margin:22px 0 8px">농가</h2>
    <div id="grid-farms"></div>
@endsection

@section('wwgrid')
<script>
    var CITY_OPTIONS = @json($cityOptions);

    wwConsole({
        el: 'grid-cities',
        editable: true,
        height: 200,
        title: '지자체',
        saveUrl: '{{ route('admin.grid.cities.save') }}',
        newRow: { name: '', region: '' },
        data: @json($cityRows),
        columns: [
            { header: '지자체명', name: 'name', width: 180, editor: 'text', sortable: true },
            { header: '광역/도', name: 'region', width: 180, editor: 'text' },
        ],
    });

    wwConsole({
        el: 'grid-farms',
        editable: true,
        height: 320,
        title: '농가',
        saveUrl: '{{ route('admin.grid.farms.save') }}',
        importUrl: '{{ route('admin.grid.farms.import') }}',
        newRow: { name: '' },
        data: @json($farmRows),
        columns: [
            { header: '농가명', name: 'name', width: 160, editor: 'text', sortable: true },
            { header: '지자체', name: 'city_id', width: 140, editor: 'combo', options: CITY_OPTIONS },
            { header: '주작물', name: 'main_crop', width: 120, editor: 'text' },
            { header: '연락처', name: 'contact_phone', width: 150, editor: 'text' },
            { header: '주소', name: 'address', width: 260, editor: 'text' },
        ],
    });
</script>
@endsection
