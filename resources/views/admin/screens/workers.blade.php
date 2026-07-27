@extends('admin.screens.layout')
@section('title', '근로자')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">근로자</h1>
            <p class="screen__sub">비민감 정보만 편집 · <strong>편집 후 [변경 저장]</strong> · <strong>번호 열 더블클릭</strong>으로 상세(입국·생활점검 이력) · 여권·생년월일·전화는 §7에 따라 미표시</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">목록</button>
        <button type="button" class="screen-tab" data-tab="detail" id="wk-detail-tab">상세</button>
    </div>

    <div data-tabpane="list">
        <div id="grid-workers"></div>
    </div>
    <div data-tabpane="detail" hidden>
        <div id="wk-detail" class="dtl"><div class="dtl-empty">목록에서 <b>번호 열</b>을 더블클릭하면 상세(입국·생활점검)가 표시됩니다.</div></div>
    </div>
@endsection

@section('wwgrid')
<script>
    function wkEsc(s) { return (s == null ? '' : String(s)); }

    function openWorker(id) {
        fetch('{{ url('admin/screen/workers') }}/' + id + '?format=json', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (d) {
                var html = '<div class="dtl-head"><b>' + wkEsc(d.name) + ' · ' + wkEsc(d.nationality) + '</b>'
                    + '<div class="dtl-head__actions"><button type="button" class="dtl-back" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></div></div>';
                html += '<dl class="dtl-dl">'
                    + '<dt>이름</dt><dd>' + wkEsc(d.name) + '</dd>'
                    + '<dt>국적</dt><dd>' + wkEsc(d.nationality) + '</dd>'
                    + '<dt>언어</dt><dd>' + wkEsc(d.locale) + '</dd>'
                    + '<dt>상태</dt><dd>' + wkEsc(d.status) + '</dd>'
                    + '<dt>소속</dt><dd>' + wkEsc((d.city || '—') + ' · ' + (d.farm || '—')) + '</dd>'
                    + '<dt>등록일</dt><dd>' + wkEsc(d.created) + '</dd></dl>';

                html += '<div class="dtl-sec"><div class="dtl-sec__title">입국·이송</div>';
                if (d.arrival) {
                    html += '<dl class="dtl-dl">'
                        + '<dt>상태</dt><dd>' + wkEsc(d.arrival.status) + '</dd>'
                        + '<dt>항공편</dt><dd>' + wkEsc(d.arrival.flight_no || '—') + '</dd>'
                        + '<dt>공항</dt><dd>' + wkEsc(d.arrival.airport || '—') + '</dd>'
                        + '<dt>예정 시각</dt><dd>' + wkEsc(d.arrival.scheduled || '—') + '</dd></dl>';
                } else { html += '<div class="dtl-empty">입국 기록이 없습니다.</div>'; }
                html += '</div>';

                html += '<div class="dtl-sec"><div class="dtl-sec__title">한국 생활 점검 이력 (' + (d.interviews || []).length + '건)</div>';
                if (d.interviews && d.interviews.length) {
                    d.interviews.forEach(function (iv) {
                        html += '<div class="dtl-hist__row"><b>' + wkEsc(iv.date) + '</b>'
                            + '<span class="dtl-badge">' + wkEsc(iv.risk) + '</span><span>' + wkEsc(iv.source) + '</span>'
                            + (iv.negatives ? '<span>이상 ' + iv.negatives + '항목</span>' : '<span>전항목 양호</span>') + '</div>';
                    });
                } else { html += '<div class="dtl-empty">점검 이력이 없습니다.</div>'; }
                html += '</div>';

                document.getElementById('wk-detail').innerHTML = html;
                document.getElementById('wk-detail-tab').hidden = false;
                window.ndnSwitchTab('detail');
            })
            .catch(function () { ndnToast('상세를 불러오지 못했습니다.', { type: 'error' }); });
    }

    wwConsole({
        el: 'grid-workers',
        editable: true,
        title: '근로자',
        saveUrl: '{{ route('admin.grid.workers.save') }}',
        importUrl: '{{ route('admin.grid.workers.import') }}',
        newRow: { nationality: 'BD', locale: 'bn', status: 'active' },
        data: @json($rows),
        columns: [
            { header: '번호', name: 'id', width: 64, align: 'center', sortable: true },
            { header: '이름', name: 'name', width: 160, editor: 'text', sortable: true },
            { header: '국적', name: 'nationality', width: 100, editor: 'combo', align: 'center',
              options: [{value:'BD',label:'방글라'},{value:'LA',label:'라오스'},{value:'LK',label:'스리랑카'},{value:'VN',label:'베트남'}] },
            { header: '언어', name: 'locale', width: 100, editor: 'combo', align: 'center',
              options: [{value:'ko',label:'한국어'},{value:'bn',label:'벵골어'},{value:'lo',label:'라오어'},{value:'si',label:'싱할라어'},{value:'vi',label:'베트남어'}] },
            { header: '상태', name: 'status', width: 110, editor: 'combo', align: 'center',
              options: [{value:'pending',label:'승인 대기'},{value:'active',label:'재직'},{value:'inactive',label:'비활성'},{value:'returned',label:'귀국'},{value:'rejected',label:'가입 거절'}] },
        ],
        onRowDblClick: function (row) { if (row.id) openWorker(row.id); },
    });
</script>
@endsection
