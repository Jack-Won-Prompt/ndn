@extends('admin.screens.layout')
@section('title', '후보자·평가')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">후보자·평가</h1>
            <p class="screen__sub">모집 명단 등록·수정 · <strong>편집 후 [변경 저장]</strong> · 엑셀 업로드/다운로드 · <strong>번호 열 더블클릭</strong>으로 후보자 상세·평가</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">목록</button>
        <button type="button" class="screen-tab" data-tab="detail" id="cd-detail-tab">상세</button>
        <button type="button" class="screen-tab" data-tab="items">평가 항목</button>
    </div>

    <div data-tabpane="list">
        <div id="grid-candidates"></div>
    </div>
    <div data-tabpane="detail" hidden>
        <div id="cd-detail" class="dtl"><div class="dtl-empty">목록에서 <b>번호 열</b>을 더블클릭하면 후보자 상세·평가가 표시됩니다.</div></div>
    </div>
    <div data-tabpane="items" hidden>
        <p class="screen__sub" style="margin:0 0 10px">
            면접 체크리스트 항목입니다. 추가·수정·삭제할 수 있고, 판정은 <strong>만점 대비 비율</strong>(합격 70% · 보류 50%)이라 배점을 바꿔도 기준이 어긋나지 않습니다.
            현재 시트 만점 <strong id="ei-total">{{ $itemsTotal }}</strong>점.
            <br><b>키</b>는 점수 저장용 식별자라 영문 소문자·숫자·밑줄만 쓰고, 한번 쓰기 시작하면 바꾸지 마세요.
        </p>
        <div id="grid-eval-items"></div>
    </div>
@endsection

@section('wwgrid')
@php
    // 국적 선택지는 Nationality enum 한 곳에서 온다. 화면마다 따로 적어 두면
    // 송출국이 늘 때 어딘가 하나가 빠진다(실제로 네팔·키르기스스탄이 빠져 있었다).
    $natOptions = collect(App\Domains\Recruitment\Enums\Nationality::adminOptions())
        ->map(fn ($label, $code) => ['value' => $code, 'label' => $label])->values();
@endphp
<script>
    function cdEsc(s) { return (s == null ? '' : String(s)); }

    function openCandidate(id) {
        fetch('{{ url('admin/candidates') }}/' + id, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (c) {
                var html = '<div class="dtl-head"><b>' + cdEsc(c.name) + ' · ' + cdEsc(c.nationality) + '</b>'
                    + '<div class="dtl-head__actions"><button type="button" class="dtl-back" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></div></div>';
                html += '<dl class="dtl-dl">'
                    + '<dt>이름</dt><dd>' + cdEsc(c.name) + '</dd>'
                    + '<dt>국적</dt><dd>' + cdEsc(c.nationality) + '</dd>'
                    + '<dt>나이</dt><dd>' + cdEsc(c.age != null ? c.age + '세' : '—') + '</dd>'
                    + '<dt>성별</dt><dd>' + cdEsc(c.gender) + '</dd>'
                    + '<dt>상태</dt><dd>' + cdEsc(c.status) + '</dd>'
                    + '<dt>대기 순번</dt><dd>' + cdEsc(c.queue_position != null ? c.queue_position : '—') + '</dd>';
                if (c.demand) {
                    html += '<dt>연계 수요</dt><dd>' + cdEsc((c.demand.farm || '—') + ' · ' + (c.demand.crop || '—')) + '</dd>';
                }
                html += '</dl>';

                html += '<div class="dtl-sec"><div class="dtl-sec__title">면접 평가 (' + (c.evaluations || []).length + '건)</div>';
                if (c.evaluations && c.evaluations.length) {
                    c.evaluations.forEach(function (e) {
                        html += '<div class="dtl-hist__row"><b>' + cdEsc(e.at || '—') + '</b>'
                            + '<span class="dtl-badge">' + cdEsc(e.result || '—') + '</span>'
                            + '<span>총점 ' + cdEsc(e.total != null ? e.total : '—') + '</span>'
                            + '<span>평가자 ' + cdEsc(e.by) + '</span>'
                            + (e.comment ? '<span>· ' + cdEsc(e.comment) + '</span>' : '') + '</div>';
                    });
                } else { html += '<div class="dtl-empty">평가 기록이 없습니다.</div>'; }
                html += '</div>';

                document.getElementById('cd-detail').innerHTML = html;
                document.getElementById('cd-detail-tab').hidden = false;
                window.ndnSwitchTab('detail');
            })
            .catch(function () { ndnToast('상세를 불러오지 못했습니다.', { type: 'error' }); });
    }

    wwConsole({
        el: 'grid-candidates',
        editable: true,
        title: '후보자',
        saveUrl: '{{ route('admin.grid.candidates.save') }}',
        importUrl: '{{ route('admin.grid.candidates.import') }}',
        newRow: { nationality: 'BD', gender: 'male', status: 'applied' },
        data: @json($rows, JSON_UNESCAPED_UNICODE),
        columns: [
            { header: '번호', name: 'id', width: 64, align: 'center', sortable: true },
            { header: '이름', name: 'name', width: 160, editor: 'text', sortable: true },
            { header: '국적', name: 'nationality', width: 100, editor: 'combo', align: 'center',
              options: @json($natOptions, JSON_UNESCAPED_UNICODE) },
            { header: '나이', name: 'age', width: 80, editor: 'number', min: 18, max: 70 },
            { header: '성별', name: 'gender', width: 90, editor: 'combo', align: 'center',
              options: [{value:'male',label:'남성'},{value:'female',label:'여성'}] },
            { header: '상태', name: 'status', width: 110, editor: 'combo', align: 'center',
              options: [{value:'applied',label:'지원'},{value:'passed',label:'합격'},{value:'held',label:'보류'},{value:'rejected',label:'불합격'}] },
            { header: '대기 순번', name: 'queue_position', width: 100, editor: 'number', min: 1 },
        ],
        onRowDblClick: function (row) { if (row.id) openCandidate(row.id); },
    });

    // 평가 항목 그리드 — 숨김 탭에서 초기화하면 폭이 0으로 깨지므로 탭이 처음 보일 때 만든다.
    var evalItemsReady = false;
    document.querySelector('.screen-tab[data-tab="items"]').addEventListener('click', function () {
        if (evalItemsReady) return;
        evalItemsReady = true;
        wwConsole({
            el: 'grid-eval-items',
            editable: true,
            title: '평가 항목',
            saveUrl: '{{ route('admin.grid.evaluation-items.save') }}',
            newRow: { key: '', label: '', hint: '', max_score: 20, sort_order: 99, active: 1 },
            data: @json($itemRows, JSON_UNESCAPED_UNICODE),
            columns: [
                { header: '번호', name: 'id', width: 64, align: 'center' },
                { header: '키(영문)', name: 'key', width: 160, editor: 'text' },
                { header: '항목명', name: 'label', width: 200, editor: 'text' },
                { header: '판단 기준', name: 'hint', width: 320, editor: 'text' },
                { header: '배점', name: 'max_score', width: 90, editor: 'number', min: 1, align: 'center' },
                { header: '순서', name: 'sort_order', width: 80, editor: 'number', min: 0, align: 'center' },
                { header: '사용', name: 'active', width: 90, editor: 'combo', align: 'center',
                  options: [{value:1,label:'사용'},{value:0,label:'미사용'}] },
            ],
        });
    });
</script>
@endsection
