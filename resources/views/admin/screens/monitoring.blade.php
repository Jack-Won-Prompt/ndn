@extends('admin.screens.layout')
@section('title', '월별 점검')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">월별 점검</h1>
            <p class="screen__sub">근로 생활 6개 항목·소속(시·농가)·이탈 리스크 · 본사 직접 입력 / 농가 방문 / 근로자 자가평가로 수집 · 위치 추적 미사용</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">목록</button>
        <button type="button" class="screen-tab" data-tab="form">점검 입력</button>
    </div>

    <div data-tabpane="list">
        <div id="grid-monitoring"></div>
    </div>

    <div data-tabpane="form" hidden>
        <div class="mi-form">
            <div class="mi-grid">
                <div class="mi-field">
                    <label>근로자 <em>*</em></label>
                    <select id="mi-worker">
                        <option value="">선택하세요</option>
                        @foreach ($workers as $w)<option value="{{ $w['value'] }}">{{ $w['label'] }}</option>@endforeach
                    </select>
                </div>
                <div class="mi-field">
                    <label>점검일 <em>*</em></label>
                    <input type="date" id="mi-date" value="{{ now(config('ndn.timezone'))->format('Y-m-d') }}">
                </div>
            </div>
            <div class="mi-chklabel">근로 생활 6개 항목 <span>(체크 = 양호, 미체크 = 이상)</span></div>
            <div class="mi-chks">
                @foreach ($itemLabels as $it)
                    <label class="mi-chk"><input type="checkbox" data-item="{{ $it['key'] }}" checked> {{ $it['label'] }}</label>
                @endforeach
            </div>
            <div class="mi-field mi-field--full">
                <label>메모</label>
                <textarea id="mi-memo" rows="2" placeholder="특이사항"></textarea>
            </div>
            <div class="mi-actions">
                <button type="button" id="mi-save" class="mi-btn">점검 저장</button>
            </div>
            <p class="mi-hint">저장 시 <b>점검자 방문(inspector)</b> 기록으로 남고, 6항목 부정 개수로 이탈 리스크가 자동 산정됩니다.</p>
        </div>
    </div>

    <style>
        .mi-form{background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:20px;max-width:720px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .mi-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px 18px;}
        .mi-field{display:flex;flex-direction:column;gap:5px;}
        .mi-field--full{grid-column:1 / -1;margin-top:14px;}
        .mi-field label{font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);}
        .mi-field label em{color:var(--mv2-pill-err-fg);font-style:normal;}
        .mi-field select,.mi-field input,.mi-field textarea{font-family:inherit;font-size:var(--mv2-fz-sm);padding:8px 10px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);background:#fff;}
        .mi-field select:focus,.mi-field input:focus,.mi-field textarea:focus{outline:none;border-color:var(--mv2-primary-500);box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        .mi-chklabel{font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);margin:16px 0 8px;}
        .mi-chklabel span{font-weight:400;color:var(--mv2-text-faint);}
        .mi-chks{display:flex;flex-wrap:wrap;gap:8px 18px;}
        .mi-chk{display:flex;align-items:center;gap:6px;font-size:var(--mv2-fz-sm);color:var(--mv2-text-strong);cursor:pointer;}
        .mi-actions{display:flex;justify-content:flex-end;margin-top:18px;}
        .mi-btn{font-family:inherit;font-size:var(--mv2-fz-sm);font-weight:700;background:var(--mv2-primary-500);color:#fff;border:0;border-radius:var(--mv2-r-sm);padding:10px 20px;cursor:pointer;}
        .mi-btn:hover{background:var(--mv2-primary-600);}
        .mi-hint{font-size:12px;color:var(--mv2-text-muted);text-align:right;margin:10px 0 0;}
        @media (max-width:640px){.mi-grid{grid-template-columns:1fr;}}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var btn = document.getElementById('mi-save');
        btn.addEventListener('click', function () {
            var wid = document.getElementById('mi-worker').value;
            var date = document.getElementById('mi-date').value;
            if (!wid) { ndnToast('근로자를 선택하세요.', { type: 'error' }); return; }
            if (!date) { ndnToast('점검일을 입력하세요.', { type: 'error' }); return; }
            var payload = { worker_id: wid, interviewed_on: date, memo: document.getElementById('mi-memo').value.trim(), items: {} };
            [].forEach.call(document.querySelectorAll('[data-tabpane="form"] input[data-item]'), function (c) {
                payload.items[c.getAttribute('data-item')] = c.checked ? 1 : 0;
            });
            btn.disabled = true; btn.textContent = '저장 중…';
            fetch('{{ route('admin.monitoring.store') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(payload),
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
              .then(function (res) {
                  if (!res.ok) {
                      var m = res.j.message || (res.j.errors ? Object.values(res.j.errors)[0][0] : '저장 실패');
                      ndnToast(m, { type: 'error' }); btn.disabled = false; btn.textContent = '점검 저장'; return;
                  }
                  ndnToast('점검이 저장되었습니다.', { type: 'success' });
                  setTimeout(function () { location.reload(); }, 900);
              })
              .catch(function () { ndnToast('저장 실패', { type: 'error' }); btn.disabled = false; btn.textContent = '점검 저장'; });
        });
    })();
</script>
@endsection

@section('wwgrid')
<script>
    wwConsole({
        el: 'grid-monitoring',
        editable: false,
        title: '월별점검',
        data: @json($rows),
        columns: [
            { header: '번호', name: 'id', width: 60, align: 'center', sortable: true },
            { header: '근로자', name: 'worker', width: 120, sortable: true },
            { header: '시·군', name: 'city', width: 110, sortable: true },
            { header: '농가', name: 'farm', width: 140, sortable: true },
            { header: '점검일', name: 'date', width: 100, align: 'center', sortable: true },
            { header: '급여', name: 'pay', width: 80, align: 'center' },
            { header: '차별없음', name: 'discrim', width: 90, align: 'center' },
            { header: '규칙', name: 'rules', width: 80, align: 'center' },
            { header: '단체생활', name: 'group', width: 90, align: 'center' },
            { header: '건강', name: 'health', width: 80, align: 'center' },
            { header: '이탈징후', name: 'flight', width: 90, align: 'center' },
            { header: '리스크', name: 'risk', width: 90, align: 'center', sortable: true },
        ],
        onRowDblClick: function (row) {
            ndnDetailModal({
                title: '월별 점검 #' + row.id,
                subtitle: row.worker + ' · ' + row.date,
                rows: [
                    ['근로자', row.worker], ['소속', row.city + ' · ' + row.farm], ['점검일', row.date],
                    ['급여 수령', row.pay], ['차별 없음', row.discrim], ['생활 규칙', row.rules],
                    ['단체 생활', row.group], ['건강', row.health], ['이탈 징후', row.flight],
                    ['이탈 리스크', row.risk], ['메모', row.memo],
                ],
            });
        },
    });
</script>
@endsection
