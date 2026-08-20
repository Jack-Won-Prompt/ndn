@extends('admin.screens.layout')
@section('title', '농가·지자체')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">농가·지자체 기준정보</h1>
            <p class="screen__sub">수요 신청·배치의 기준이 되는 지자체·농가 정보 등록·수정 · <strong>각 표 편집 후 [변경 저장]</strong></p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="city">지자체</button>
        <button type="button" class="screen-tab" data-tab="farm">농가</button>
    </div>

    <div data-tabpane="city">
        <div id="grid-cities"></div>
    </div>
    <div data-tabpane="farm" hidden>
        <div id="grid-farms"></div>
    </div>
@endsection

@section('wwgrid')
<script>
    var CITY_OPTIONS = @json($cityOptions);

    // 지자체 그리드 — 기본 탭이라 즉시 초기화
    wwConsole({
        el: 'grid-cities',
        editable: true,
        title: '지자체',
        saveUrl: '{{ route('admin.grid.cities.save') }}',
        newRow: { name: '', region: '', quota: '', recruiting: 1 },
        data: @json($cityRows),
        columns: [
            { header: '지자체명', name: 'name', width: 180, editor: 'text', sortable: true },
            { header: '광역/도', name: 'region', width: 180, editor: 'text' },
            // 지역별 모집 조건 — 정원이 차거나 모집을 끄면 그 지역 가입이 막힌다
            { header: '모집 정원', name: 'quota', width: 110, editor: 'text', align: 'center' },
            { header: '모집 여부', name: 'recruiting', width: 110, editor: 'combo', align: 'center',
              options: [{value:1,label:'모집 중'},{value:0,label:'중지'}] },
        ],
    });

    // 농가 그리드 — 숨김 탭에서 초기화하면 폭이 0으로 깨지므로, 탭이 처음 표시될 때 초기화
    var farmsReady = false;
    function initFarms() {
        if (farmsReady) return; farmsReady = true;
        wwConsole({
            el: 'grid-farms',
            editable: true,
            title: '농가',
            saveUrl: '{{ route('admin.grid.farms.save') }}',
            importUrl: '{{ route('admin.grid.farms.import') }}',
            // 농가는 기준정보다 — 지우면 매달린 화면들도 함께 정리된다.
            deleteWarning: '삭제하면 그 농가의 수요·배정·입국 기록·방문 점검·점검표도 함께 정리되고, 배정돼 있던 근로자는 미배정으로 풀립니다.',
            newRow: { name: '' },
            data: @json($farmRows),
            columns: [
                { header: '농가명', name: 'name', width: 160, editor: 'text', sortable: true },
                { header: '지자체', name: 'city_id', width: 140, editor: 'combo', options: CITY_OPTIONS },
                { header: '주작물', name: 'main_crop', width: 120, editor: 'text' },
                { header: '연락처', name: 'contact_phone', width: 150, editor: 'text' },
                { header: '주소', name: 'address', width: 260, editor: 'text' },
                // 지자체 배정 신청서에 함께 적어 내는 번호. 숫자지만 앞자리 0 이 살아야 해
                // number 가 아니라 text 로 둔다.
                { header: '경영체등록번호', name: 'business_reg_no', width: 150, editor: 'text' },
            ],
        });
    }
    // 탭 전환(ui.js)이 패널을 보이게 한 다음(setTimeout 0) 그리드를 초기화한다.
    document.querySelector('.screen-tab[data-tab="farm"]').addEventListener('click', function () {
        setTimeout(initFarms, 0);
    });
</script>
@endsection
