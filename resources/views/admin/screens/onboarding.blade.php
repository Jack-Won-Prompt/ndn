@extends('admin.screens.layout')
@section('title', '온보딩 검수')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">온보딩 검수</h1>
            <p class="screen__sub">행 더블클릭으로 제출내용·전자서명 확인(감사 로그 기록) · 본인 기입 정보는 암호화 저장</p>
        </div>
    </div>

    <div id="grid-onboarding"></div>
@endsection

@section('wwgrid')
<script>
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
        onRowDblClick: function (row) {
            var LABELS = { address_kr: '국내 주소', emergency_contact: '비상 연락처' };
            fetch('{{ url('admin/onboarding') }}/' + row.id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (o) {
                    var rows = [['근로자', o.worker], ['상태', o.status], ['제출일시', o.submitted_at]];
                    var p = o.payload || {};
                    Object.keys(p).forEach(function (k) {
                        var v = p[k];
                        rows.push([LABELS[k] || k, (v && typeof v === 'object') ? JSON.stringify(v) : v]);
                    });
                    rows.push(['전자서명', o.has_signature ? '첨부됨' : '없음']);
                    if (o.review_note) rows.push(['검수 메모', o.review_note]);
                    ndnDetailModal({
                        title: '온보딩 #' + o.id, subtitle: o.worker, rows: rows,
                        links: o.signature_url ? [{ label: '전자서명 파일 열기', href: o.signature_url }] : [],
                        note: '본인 기입 정보 열람은 감사 로그에 기록됩니다.',
                    });
                })
                .catch(function () { ndnToast('상세를 불러오지 못했습니다.', { type: 'error' }); });
        },
    });
</script>
@endsection
