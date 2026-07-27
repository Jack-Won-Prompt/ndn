@extends('admin.screens.layout')
@section('title', '온보딩 검수')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">온보딩 검수</h1>
            <p class="screen__sub">행 더블클릭으로 상세 탭 열람 · 본인 기입 정보·제출 서류(전자서명) 확인(감사 로그 기록) · 암호화 저장</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">목록</button>
        <button type="button" class="screen-tab" data-tab="detail" id="ob-detail-tab">상세</button>
    </div>

    <div data-tabpane="list">
        <div id="grid-onboarding"></div>
    </div>
    <div data-tabpane="detail" hidden>
        <div id="ob-detail" class="dtl"><div class="dtl-empty">목록에서 행을 더블클릭하면 상세·제출 서류가 표시됩니다.</div></div>
    </div>
@endsection

@section('wwgrid')
<script>
    var OB_LABELS = { address_kr: '국내 주소', emergency_contact: '비상 연락처' };
    function obEsc(s) { return (s == null ? '' : String(s)); }

    function openOnboarding(id) {
        fetch('{{ url('admin/onboarding') }}/' + id, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (o) {
                var html = '<div class="dtl-head"><b>온보딩 #' + o.id + ' · ' + obEsc(o.worker) + '</b>'
                    + '<div class="dtl-head__actions"><button type="button" class="dtl-back" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></div></div>';
                var rows = [['근로자', o.worker], ['상태', o.status], ['제출일시', o.submitted_at]];
                if (o.review_note) rows.push(['검수 메모', o.review_note]);
                html += '<dl class="dtl-dl">';
                rows.forEach(function (r) { html += '<dt>' + obEsc(r[0]) + '</dt><dd>' + obEsc(r[1]) + '</dd>'; });
                var p = o.payload || {};
                Object.keys(p).forEach(function (k) {
                    var v = p[k];
                    html += '<dt>' + obEsc(OB_LABELS[k] || k) + '</dt><dd>' + obEsc((v && typeof v === 'object') ? JSON.stringify(v) : v) + '</dd>';
                });
                html += '</dl>';
                html += '<div class="dtl-sec"><div class="dtl-sec__title">제출 서류</div>';
                if (o.has_signature && o.signature_url) {
                    html += '<div class="dtl-docs"><div class="dtl-doc"><a href="' + o.signature_url + '" target="_blank">'
                        + '<img src="' + o.signature_url + '" alt="전자서명" loading="lazy"></a><div class="dtl-doc__name">전자서명</div></div></div>';
                } else {
                    html += '<div class="dtl-empty">제출된 서류(전자서명)가 없습니다.</div>';
                }
                html += '</div>';
                document.getElementById('ob-detail').innerHTML = html;
                document.getElementById('ob-detail-tab').hidden = false;
                window.ndnSwitchTab('detail');
            })
            .catch(function () { ndnToast('상세를 불러오지 못했습니다.', { type: 'error' }); });
    }

    wwConsole({
        el: 'grid-onboarding',
        editable: false,
        title: '온보딩',
        data: @json($rows),
        columns: [
            { header: '번호', name: 'id', width: 70, align: 'center', sortable: true },
            { header: '근로자', name: 'worker', width: 160, sortable: true },
            { header: '상태', name: 'status', width: 130, align: 'center' },
            { header: '제출일시', name: 'submitted', width: 170, align: 'center', sortable: true },
            { header: '검수 메모', name: 'note', width: 260 },
        ],
        onRowDblClick: function (row) { openOnboarding(row.id); },
    });
</script>
@endsection
