@extends('admin.screens.layout')
@section('title', '근무상태 점검표')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">근무상태 종합 점검표</h1>
            <p class="screen__sub">근로자 한 사람에 대한 현장 점검 · 근태·업무능력·생활·안전 43항목 · 응답으로 이탈 리스크 산정 (위치 추적 미사용)</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">점검 목록</button>
        <button type="button" class="screen-tab" data-tab="form">점검 작성</button>
    </div>

    <div data-tabpane="list">
        <div id="grid-workreviews"></div>
    </div>

    <div data-tabpane="form" hidden>
        <div class="wr-form">
            <h2 class="wr-h2">점검 개요</h2>
            <div class="wr-grid">
                <div class="wr-field">
                    <label>근로자 <em>*</em></label>
                    <select id="wr-worker">
                        <option value="">선택하세요</option>
                        @foreach ($workers as $w)
                            <option value="{{ $w['value'] }}" data-farm="{{ $w['farm_id'] }}">{{ $w['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="wr-help" id="wr-farm-note">농가는 확정된 배정에서 자동으로 정해집니다.</p>
                </div>
                <div class="wr-field">
                    <label>점검일시 <em>*</em></label>
                    <input type="datetime-local" id="wr-at" value="{{ now(config('ndn.timezone'))->format('Y-m-d\TH:i') }}">
                </div>
                <div class="wr-field">
                    <label>점검장소</label>
                    <input type="text" id="wr-place" maxlength="200" placeholder="예: 농가 작업장 / 숙소">
                </div>
                <div class="wr-field">
                    <label>점검유형 <em>*</em></label>
                    <select id="wr-type">
                        @foreach ($typeOptions as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
            </div>

            @foreach ($sections as $s)
                <h2 class="wr-h2">{{ $s['label'] }}</h2>
                <table class="wr-items" data-section="{{ $s['key'] }}">
                    <thead>
                        <tr>
                            <th>점검항목</th>
                            @foreach ($s['options'] as $ov => $ol)<th style="width:96px">{{ $ol }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s['items'] as $item)
                            <tr>
                                <td>{{ $item['label'] }}</td>
                                @foreach ($s['options'] as $ov => $ol)
                                    <td class="c">
                                        <input type="radio" name="wr-item-{{ $item['id'] }}" value="{{ $ov }}"
                                               data-item="{{ $item['id'] }}" @checked($loop->first)>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach

            <h2 class="wr-h2">연장근무 내역</h2>
            <div class="wr-grid">
                <div class="wr-field">
                    <label>실시 여부</label>
                    <select id="wr-ot-done"><option value="">—</option><option value="1">예</option><option value="0">아니오</option></select>
                </div>
                <div class="wr-field">
                    <label>연장근무 시간</label>
                    <input type="number" id="wr-ot-hours" min="0" max="999" step="0.5" placeholder="시간">
                </div>
                <div class="wr-field">
                    <label>근로자 동의 여부</label>
                    <select id="wr-ot-consent"><option value="">—</option><option value="1">예</option><option value="0">아니오</option></select>
                </div>
            </div>

            <h2 class="wr-h2">임금 및 계약사항</h2>
            <div class="wr-grid">
                <div class="wr-field">
                    <label>월 평균 임금</label>
                    <input type="text" id="wr-wage" maxlength="50" placeholder="예: 1,800,000원">
                    <p class="wr-help">개인 급여액이라 암호화해 보관합니다.</p>
                </div>
                <div class="wr-field">
                    <label>최근 임금 지급일</label>
                    <input type="date" id="wr-paid-on">
                </div>
                <div class="wr-field">
                    <label>임금 체불</label>
                    <select id="wr-unpaid"><option value="0">없음</option><option value="1">있음</option></select>
                    <p class="wr-help">있음으로 두면 다른 점수와 무관하게 고위험으로 잡힙니다.</p>
                </div>
                <div class="wr-field">
                    <label>숙식 제공</label>
                    <select id="wr-board"><option value="">—</option><option value="1">예</option><option value="0">아니오</option></select>
                </div>
                <div class="wr-field">
                    <label>근로계약 준수</label>
                    <select id="wr-contract"><option value="">—</option><option value="1">예</option><option value="0">아니오</option></select>
                </div>
                <div class="wr-field wr-field--full">
                    <label>계약 위반 사항</label>
                    <textarea id="wr-violation" rows="2"></textarea>
                </div>
            </div>

            <h2 class="wr-h2">종합 의견</h2>
            <div class="wr-grid">
                <div class="wr-field">
                    <label>점검 결과 <em>*</em></label>
                    <select id="wr-result">
                        @foreach ($resultOptions as $v => $l)<option value="{{ $v }}" @selected($v === 'good')>{{ $l }}</option>@endforeach
                    </select>
                    <p class="wr-help">[특별관리 대상]은 다른 점수와 무관하게 고위험으로 잡힙니다.</p>
                </div>
            </div>
            <div class="wr-grid">
                <div class="wr-field wr-field--full"><label>주요 특이사항</label><textarea id="wr-notable" rows="2"></textarea></div>
                <div class="wr-field wr-field--full"><label>개선 요구사항</label><textarea id="wr-improve" rows="2"></textarea></div>
                <div class="wr-field wr-field--full"><label>농가 건의사항</label><textarea id="wr-requests" rows="2"></textarea></div>
            </div>

            <h2 class="wr-h2">향후 조치사항</h2>
            <div class="wr-grid">
                <div class="wr-field"><label>개선기한</label><input type="date" id="wr-due"></div>
                <div class="wr-field"><label>담당자</label><input type="text" id="wr-assignee" maxlength="100"></div>
                <div class="wr-field"><label>재점검 예정일</label><input type="date" id="wr-recheck"></div>
                <div class="wr-field">
                    <label>보고 필요</label>
                    <div class="wr-checks">
                        <label><input type="checkbox" id="wr-report-city"> 지자체</label>
                        <label><input type="checkbox" id="wr-report-imm"> 출입국사무소</label>
                    </div>
                </div>
                <div class="wr-field wr-field--full"><label>기타 조치사항</label><textarea id="wr-action-note" rows="2"></textarea></div>
            </div>

            <h2 class="wr-h2">확인 및 서명</h2>
            <div class="wr-grid">
                <div class="wr-field"><label>점검자</label><input type="text" id="wr-sign-inspector" maxlength="100" value="{{ $me }}"></div>
                <div class="wr-field"><label>농가 대표</label><input type="text" id="wr-sign-farm" maxlength="100"></div>
                <div class="wr-field"><label>외국인근로자</label><input type="text" id="wr-sign-worker" maxlength="100"></div>
                <div class="wr-field"><label>통역인(해당 시)</label><input type="text" id="wr-sign-interpreter" maxlength="100"></div>
            </div>
            <p class="wr-help">서명란에는 확인한 사람의 이름을 적습니다. 서명 이미지는 아직 받지 않습니다.</p>

            <div class="wr-actions">
                <button type="button" id="wr-save" class="wr-btn">점검표 저장</button>
            </div>
        </div>
    </div>

    <style>
        .wr-form{background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:22px;max-width:900px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .wr-h2{font-size:var(--mv2-fz-sm);font-weight:800;color:var(--mv2-text-strong);margin:26px 0 10px;padding-bottom:8px;border-bottom:1px solid var(--mv2-border-soft);}
        .wr-h2:first-child{margin-top:0;}
        .wr-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 18px;}
        .wr-field{display:flex;flex-direction:column;gap:5px;}
        .wr-field--full{grid-column:1 / -1;}
        .wr-field label{font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);}
        .wr-field label em{color:var(--mv2-pill-err-fg);font-style:normal;}
        .wr-field select,.wr-field input,.wr-field textarea{font-family:inherit;font-size:var(--mv2-fz-sm);padding:8px 10px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);background:#fff;}
        .wr-field select:focus,.wr-field input:focus,.wr-field textarea:focus{outline:none;border-color:var(--mv2-primary-500);box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        .wr-help{font-size:12px;color:var(--mv2-text-faint);margin:0;}
        .wr-checks{display:flex;gap:14px;align-items:center;font-size:var(--mv2-fz-sm);padding-top:6px;}
        .wr-checks label{display:flex;align-items:center;gap:5px;font-weight:400;color:var(--mv2-text-strong);cursor:pointer;}
        .wr-items{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);border:1px solid var(--mv2-border-soft);border-radius:var(--mv2-r-sm);overflow:hidden;}
        .wr-items thead th{background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-size:var(--mv2-fz-xs);font-weight:700;text-align:left;padding:8px 12px;border-bottom:1px solid var(--mv2-border-soft);}
        .wr-items thead th+th{text-align:center;}
        .wr-items tbody td{padding:7px 12px;border-bottom:1px solid var(--mv2-border-soft);color:var(--mv2-text-strong);}
        .wr-items tbody tr:last-child td{border-bottom:0;}
        .wr-items td.c{text-align:center;}
        .wr-actions{display:flex;justify-content:flex-end;margin-top:22px;}
        .wr-btn{font-family:inherit;font-size:var(--mv2-fz-sm);font-weight:700;background:var(--mv2-primary-500);color:#fff;border:0;border-radius:var(--mv2-r-sm);padding:10px 22px;cursor:pointer;}
        .wr-btn:hover{background:var(--mv2-primary-600);}
        @media (max-width:820px){.wr-grid{grid-template-columns:1fr;}}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var btn = document.getElementById('wr-save');

        function val(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; }
        function tri(id) { var v = val(id); return v === '' ? null : v === '1'; }

        document.getElementById('wr-worker').addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            var farm = opt ? opt.getAttribute('data-farm') : '';
            document.getElementById('wr-farm-note').textContent = farm
                ? '농가는 확정된 배정에서 자동으로 정해집니다.'
                : '이 근로자는 확정된 배정이 없어 점검표를 저장할 수 없습니다.';
        });

        btn.addEventListener('click', function () {
            if (!val('wr-worker')) { ndnToast('근로자를 선택하세요.', { type: 'error' }); return; }
            if (!val('wr-at')) { ndnToast('점검일시를 입력하세요.', { type: 'error' }); return; }

            var answers = {};
            [].forEach.call(document.querySelectorAll('.wr-items input[type="radio"]:checked'), function (r) {
                answers[r.getAttribute('data-item')] = r.value;
            });

            var payload = {
                worker_id: val('wr-worker'),
                reviewed_at: val('wr-at'),
                place: val('wr-place'),
                review_type: val('wr-type'),
                overtime_done: tri('wr-ot-done'),
                overtime_hours: val('wr-ot-hours') === '' ? null : val('wr-ot-hours'),
                overtime_consented: tri('wr-ot-consent'),
                avg_monthly_wage: val('wr-wage') || null,
                last_paid_on: val('wr-paid-on') || null,
                wage_unpaid: val('wr-unpaid') === '1',
                board_provided: tri('wr-board'),
                contract_followed: tri('wr-contract'),
                contract_violation: val('wr-violation') || null,
                result: val('wr-result'),
                notable: val('wr-notable') || null,
                improvements: val('wr-improve') || null,
                farm_requests: val('wr-requests') || null,
                action_due_on: val('wr-due') || null,
                action_assignee: val('wr-assignee') || null,
                recheck_on: val('wr-recheck') || null,
                report_city: document.getElementById('wr-report-city').checked,
                report_immigration: document.getElementById('wr-report-imm').checked,
                action_note: val('wr-action-note') || null,
                signed_inspector: val('wr-sign-inspector') || null,
                signed_farm: val('wr-sign-farm') || null,
                signed_worker: val('wr-sign-worker') || null,
                signed_interpreter: val('wr-sign-interpreter') || null,
                answers: answers,
            };

            btn.disabled = true; btn.textContent = '저장 중…';
            fetch('{{ route('admin.work-reviews.store') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(payload),
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok || !res.j.ok) {
                        var m = res.j.message || (res.j.errors ? Object.values(res.j.errors)[0][0] : '저장하지 못했습니다.');
                        ndnToast(m, { type: 'error' });
                        btn.disabled = false; btn.textContent = '점검표 저장';
                        return;
                    }
                    ndnToast('점검표를 저장했습니다. 이탈 리스크: ' + res.j.risk + ' (' + res.j.score + '점)', { type: 'success' });
                    setTimeout(function () { location.reload(); }, 1200);
                })
                .catch(function () {
                    ndnToast('저장하지 못했습니다.', { type: 'error' });
                    btn.disabled = false; btn.textContent = '점검표 저장';
                });
        });
    })();
</script>
@endsection

@section('wwgrid')
<script>
    wwConsole({
        el: 'grid-workreviews',
        editable: false,
        title: '근무상태점검표',
        data: @json($rows),
        columns: [
            { header: '번호', name: 'id', width: 60, align: 'center', sortable: true },
            { header: '근로자', name: 'worker', width: 120, sortable: true },
            { header: '시·군', name: 'city', width: 100, sortable: true },
            { header: '농가', name: 'farm', width: 140, sortable: true },
            { header: '점검일시', name: 'date', width: 140, align: 'center', sortable: true },
            { header: '유형', name: 'type', width: 90, align: 'center', sortable: true },
            { header: '점검 결과', name: 'result', width: 110, align: 'center', sortable: true },
            { header: '이탈 리스크', name: 'risk', width: 100, align: 'center', sortable: true },
            { header: '점수', name: 'score', width: 70, align: 'center', sortable: true },
            { header: '점검자', name: 'inspector', width: 100 },
            { header: '재점검', name: 'recheck', width: 100, align: 'center' },
            { header: '보고', name: 'report', width: 110, align: 'center' },
        ],
        onRowDblClick: function (row) {
            fetch('{{ url('admin/work-reviews') }}/' + row.id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (d) {
                    var rows = [
                        ['근로자', d.worker + ' (' + d.nationality + ')'],
                        ['소속', (d.city || '—') + ' · ' + (d.farm || '—')],
                        ['점검일시', d.reviewed_at], ['점검장소', d.place || '—'],
                        ['점검유형', d.type], ['점검자', d.inspector || '—'],
                        ['점검 결과', d.result], ['이탈 리스크', d.risk + ' (' + d.score + '점)'],
                    ];
                    d.sections.forEach(function (s) {
                        var lines = s.answers.map(function (a) {
                            return (a.bad ? '⚠ ' : '· ') + a.label + ' — ' + a.value + (a.note ? ' (' + a.note + ')' : '');
                        });
                        rows.push([s.label, lines.length ? lines.join('\n') : '응답 없음']);
                    });
                    rows.push(['임금', '월 평균 ' + (d.wage.avg || '—')
                        + ' · 최근 지급 ' + (d.wage.last_paid_on || '—')
                        + ' · 체불 ' + (d.wage.unpaid ? '있음' : '없음')]);
                    rows.push(['주요 특이사항', d.opinion.notable || '—']);
                    rows.push(['개선 요구사항', d.opinion.improvements || '—']);
                    rows.push(['농가 건의사항', d.opinion.farm_requests || '—']);
                    rows.push(['향후 조치', '개선기한 ' + (d.actions.due_on || '—')
                        + ' · 담당 ' + (d.actions.assignee || '—')
                        + ' · 재점검 ' + (d.actions.recheck_on || '—')]);
                    rows.push(['보고 필요', (d.actions.report_city ? '지자체 ' : '') + (d.actions.report_immigration ? '출입국' : '')
                        || '없음']);
                    rows.push(['서명', ['점검자 ' + (d.signatures.inspector || '—'),
                        '농가 ' + (d.signatures.farm || '—'),
                        '근로자 ' + (d.signatures.worker || '—'),
                        '통역 ' + (d.signatures.interpreter || '—')].join(' · ')]);

                    ndnDetailModal({
                        title: '근무상태 점검표 #' + d.id,
                        subtitle: d.worker + ' · ' + d.reviewed_at,
                        rows: rows,
                    });
                })
                .catch(function () { ndnToast('점검표를 불러오지 못했습니다.', { type: 'error' }); });
        },
    });
</script>
@endsection
