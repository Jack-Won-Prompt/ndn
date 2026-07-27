@extends('admin.screens.layout')
@section('title', '농가 방문 점검')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">농가 방문 점검</h1>
            <p class="screen__sub">본사 월별 농가 방문 · 농가 상태·근로자 근무 현황·애로사항 등록 + 현장 사진 여러 장 업로드(방문 증빙) · 위치정보 미저장(§7-2)</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">목록</button>
        <button type="button" class="screen-tab" data-tab="detail" id="fv-detail-tab" hidden>상세</button>
        <button type="button" class="screen-tab" data-tab="form">방문 등록</button>
    </div>

    {{-- 등록 폼 (탭) --}}
    <div data-tabpane="form" hidden>
    <div class="fv-form">
        <div class="fv-grid">
            <div class="fv-field">
                <label>농가 <em>*</em></label>
                <select id="fv-farm">
                    @foreach ($farms as $f)<option value="{{ $f['value'] }}">{{ $f['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="fv-field">
                <label>방문일 <em>*</em></label>
                <input type="date" id="fv-date" value="{{ now(config('ndn.timezone'))->format('Y-m-d') }}">
            </div>
            <div class="fv-field">
                <label>농가 상태 <em>*</em></label>
                <select id="fv-farm-status">
                    @foreach ($statuses as $s)<option value="{{ $s['value'] }}">{{ $s['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="fv-field">
                <label>근로자 근무 상태 <em>*</em></label>
                <select id="fv-worker-status">
                    @foreach ($statuses as $s)<option value="{{ $s['value'] }}">{{ $s['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="fv-field">
                <label>재직 인원</label>
                <input type="number" id="fv-headcount" min="0" max="999" placeholder="예: 5">
            </div>
            <div class="fv-field fv-field--full">
                <label>근무 현황</label>
                <textarea id="fv-work" rows="2" placeholder="근태·초과근무·업무 배치 등"></textarea>
            </div>
            <div class="fv-field fv-field--full">
                <label>애로사항</label>
                <textarea id="fv-issue" rows="2" placeholder="농가·근로자가 겪는 어려움"></textarea>
            </div>
            <div class="fv-field fv-field--full">
                <label>조치·후속사항</label>
                <textarea id="fv-action" rows="2" placeholder="현장 조치 및 후속 계획"></textarea>
            </div>
            <div class="fv-field fv-field--full">
                <label>종합 메모</label>
                <textarea id="fv-memo" rows="2"></textarea>
            </div>
            <div class="fv-field fv-field--full">
                <label>현장 사진 (여러 장 · 장당 최대 10MB)</label>
                <input type="file" id="fv-photos" accept="image/*" multiple>
                <div id="fv-preview" class="fv-preview"></div>
            </div>
            <div class="fv-field fv-field--full">
                <label>근로자 인터뷰 <span style="font-weight:400;color:var(--mv2-text-faint)">(6항목 · 체크=양호, 미체크=이상)</span></label>
                <div id="fv-workers" class="fv-workers"><div class="fv-workers__empty">농가를 선택하면 배정 근로자가 표시됩니다.</div></div>
            </div>
        </div>
        <div class="fv-actions">
            <button type="button" id="fv-save" class="fv-btn">방문 점검 저장</button>
        </div>
    </div>
    </div>{{-- /탭:form --}}

    {{-- 목록 (탭) --}}
    <div data-tabpane="list">
    <div class="fv-listwrap">
        <table class="fv-table" id="fv-table">
            <thead>
                <tr>
                    <th style="width:56px">번호</th>
                    <th>농가</th>
                    <th style="width:110px">방문일</th>
                    <th style="width:120px">점검자</th>
                    <th style="width:90px">농가</th>
                    <th style="width:90px">근무</th>
                    <th style="width:70px">인원</th>
                    <th style="width:70px">사진</th>
                    <th style="width:70px">애로</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr data-id="{{ $r['id'] }}">
                        <td class="c">{{ $r['id'] }}</td>
                        <td>{{ $r['farm'] }}</td>
                        <td class="c">{{ $r['visited_on'] }}</td>
                        <td>{{ $r['inspector'] }}</td>
                        <td class="c"><span class="fv-badge fv-badge--{{ $r['farm_status'] }}">{{ $r['farm_status_label'] }}</span></td>
                        <td class="c"><span class="fv-badge fv-badge--{{ $r['worker_status'] }}">{{ $r['worker_status_label'] }}</span></td>
                        <td class="c">{{ $r['headcount'] ?? '—' }}</td>
                        <td class="c">{{ $r['photos'] ? '📷 '.$r['photos'] : '—' }}</td>
                        <td class="c">{{ $r['has_issue'] ? '⚠' : '—' }}</td>
                    </tr>
                @empty
                    <tr id="fv-empty"><td colspan="9" class="fv-emptyrow">등록된 방문 점검이 없습니다. 위 폼에서 등록하세요.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>{{-- /탭:list --}}

    {{-- 상세 (목록에서 행 더블클릭 시 이 탭으로) --}}
    <div data-tabpane="detail" hidden>
        <div id="fv-detail" class="fv-detailwrap"></div>
    </div>{{-- /탭:detail --}}

    <style>
        .fv-form{background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:18px;margin-bottom:14px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .fv-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 16px;}
        .fv-field{display:flex;flex-direction:column;gap:5px;}
        .fv-field--full{grid-column:1 / -1;}
        .fv-field label{font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);}
        .fv-field label em{color:var(--mv2-pill-err-fg);font-style:normal;}
        .fv-field input,.fv-field select,.fv-field textarea{font-family:inherit;font-size:var(--mv2-fz-sm);padding:8px 10px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);background:#fff;}
        .fv-field input:focus,.fv-field select:focus,.fv-field textarea:focus{outline:none;border-color:var(--mv2-primary-500);box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        .fv-preview{display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;}
        .fv-preview img{width:78px;height:78px;object-fit:cover;border-radius:8px;border:1px solid var(--mv2-border-soft);}
        .fv-actions{display:flex;justify-content:flex-end;margin-top:16px;}
        .fv-btn{font-family:inherit;font-size:var(--mv2-fz-sm);font-weight:700;background:var(--mv2-primary-500);color:#fff;border:0;border-radius:var(--mv2-r-sm);padding:10px 20px;cursor:pointer;}
        .fv-btn:hover{background:var(--mv2-primary-600);}
        .fv-listwrap{border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 20px rgba(15,23,42,.05);}
        .fv-table{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);}
        .fv-table thead th{text-align:left;background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-weight:700;font-size:var(--mv2-fz-xs);padding:10px 14px;border-bottom:1px solid var(--mv2-border-soft);white-space:nowrap;}
        .fv-table tbody td{padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);color:var(--mv2-text-strong);}
        .fv-table tbody tr:last-child td{border-bottom:0;}
        .fv-table tbody tr[data-id]{cursor:pointer;}
        .fv-table tbody tr[data-id]:hover{background:var(--mv2-slate-25);}
        .fv-table td.c{text-align:center;}
        .fv-emptyrow{text-align:center;color:var(--mv2-text-faint);padding:34px 0;}
        .fv-badge{font-size:11px;font-weight:700;border-radius:100px;padding:2px 9px;}
        .fv-badge--normal{background:#E7F6EC;color:#1B7F43;}
        .fv-badge--caution{background:#FEF3C7;color:#8a6d00;}
        .fv-badge--issue{background:var(--mv2-pill-err-bg);color:var(--mv2-pill-err-fg);}
        .fv-detailwrap{background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:20px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 20px rgba(15,23,42,.05);}
        .fv-detail-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
        .fv-detail-head b{font-size:var(--mv2-fz-md);color:var(--mv2-text-strong);}
        .fv-back{font-family:inherit;font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-primary-600);background:none;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);padding:5px 12px;cursor:pointer;}
        .fv-back:hover{background:var(--mv2-primary-50,#E9F6F4);}
        .fv-dl{display:grid;grid-template-columns:120px 1fr;gap:0;margin:0;}
        .fv-dl dt{color:var(--mv2-text-muted);font-size:var(--mv2-fz-xs);font-weight:700;padding:8px 0;border-bottom:1px solid var(--mv2-border-soft);}
        .fv-dl dd{margin:0;font-size:var(--mv2-fz-sm);padding:8px 0;border-bottom:1px solid var(--mv2-border-soft);white-space:pre-wrap;}
        .fv-gallery{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px;}
        .fv-gallery a{display:block;}
        .fv-gallery img{width:120px;height:120px;object-fit:cover;border-radius:10px;border:1px solid var(--mv2-border-soft);}
        .fv-workers{display:flex;flex-direction:column;gap:8px;}
        .fv-workers__empty{color:var(--mv2-text-faint);font-size:var(--mv2-fz-sm);padding:8px 0;}
        .fv-worker{border:1px solid var(--mv2-border-soft);border-radius:8px;padding:10px 12px;background:var(--mv2-slate-25);}
        .fv-worker__head{display:flex;align-items:center;gap:6px;margin-bottom:8px;}
        .fv-worker__name{font-weight:700;font-size:var(--mv2-fz-sm);color:var(--mv2-text-strong);}
        .fv-worker__nat{font-size:11px;color:var(--mv2-text-muted);font-weight:600;}
        .fv-chks{display:flex;flex-wrap:wrap;gap:6px 14px;margin-bottom:8px;}
        .fv-chk{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--mv2-text-strong);cursor:pointer;}
        .fv-chk input{width:auto;}
        .fv-worker__memo{width:100%;font-family:inherit;font-size:var(--mv2-fz-xs);padding:6px 9px;border:1px solid var(--mv2-border-default);border-radius:6px;}
        .fv-ivs{margin-top:16px;}
        .fv-ivs__title{font-size:var(--mv2-fz-sm);font-weight:700;color:var(--mv2-text-strong);margin:0 0 8px;}
        .fv-iv{border:1px solid var(--mv2-border-soft);border-radius:8px;padding:10px 12px;margin-bottom:8px;}
        .fv-iv__head{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;}
        .fv-iv__worker{font-weight:700;font-size:var(--mv2-fz-sm);}
        .fv-iv__right{display:flex;align-items:center;gap:8px;}
        .fv-iv__items{display:flex;flex-wrap:wrap;gap:5px;}
        .fv-tag-ok{font-size:11px;padding:2px 7px;border-radius:100px;background:#E7F6EC;color:#1B7F43;}
        .fv-tag-bad{font-size:11px;padding:2px 7px;border-radius:100px;background:var(--mv2-pill-err-bg);color:var(--mv2-pill-err-fg);}
        .fv-risk{font-size:11px;font-weight:700;padding:2px 9px;border-radius:100px;}
        .fv-risk--low{background:#E7F6EC;color:#1B7F43;}
        .fv-risk--medium{background:#FEF3C7;color:#8a6d00;}
        .fv-risk--high{background:var(--mv2-pill-err-bg);color:var(--mv2-pill-err-fg);}
        .fv-histbtn{font-family:inherit;font-size:11px;font-weight:700;color:var(--mv2-primary-600);background:none;border:1px solid var(--mv2-border-default);border-radius:6px;padding:3px 9px;cursor:pointer;}
        .fv-histbtn:hover{background:var(--mv2-primary-50,#E9F6F4);}
        .fv-iv__memo{font-size:12px;color:var(--mv2-text-muted);margin-top:6px;}
        .fv-hist{margin-top:8px;border-top:1px dashed var(--mv2-border-soft);padding-top:6px;}
        .fv-hist__row{font-size:12px;color:var(--mv2-text-muted);padding:3px 0;display:flex;gap:10px;align-items:center;flex-wrap:wrap;}
        @media (max-width:820px){.fv-grid{grid-template-columns:1fr;}}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/farm-visits') }}';
        var input = document.getElementById('fv-photos');
        var ITEMS = @json($itemLabels);

        function esc(s) { return (s == null ? '' : String(s)); }

        // 농가 선택 → 배정 근로자별 인터뷰(6항목) 폼 로드
        var farmSel = document.getElementById('fv-farm');
        function loadWorkers() {
            var box = document.getElementById('fv-workers');
            if (!farmSel.value) { box.innerHTML = '<div class="fv-workers__empty">농가를 선택하세요.</div>'; return; }
            box.innerHTML = '<div class="fv-workers__empty">불러오는 중…</div>';
            fetch(BASE + '/farms/' + farmSel.value + '/workers', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    var ws = j.workers || [];
                    if (!ws.length) { box.innerHTML = '<div class="fv-workers__empty">이 농가에 배정 확정된 근로자가 없습니다. (농가 정보만 등록됩니다)</div>'; return; }
                    box.innerHTML = '';
                    ws.forEach(function (w) {
                        var chks = ITEMS.map(function (it) {
                            return '<label class="fv-chk"><input type="checkbox" data-item="' + it.key + '" checked> ' + esc(it.label) + '</label>';
                        }).join('');
                        var card = document.createElement('div');
                        card.className = 'fv-worker'; card.setAttribute('data-wid', w.id);
                        card.innerHTML = '<div class="fv-worker__head"><span class="fv-worker__name">' + esc(w.name) + '</span><span class="fv-worker__nat">' + esc(w.nationality) + '</span></div>'
                            + '<div class="fv-chks">' + chks + '</div>'
                            + '<input type="text" class="fv-worker__memo" placeholder="인터뷰 메모(선택)">';
                        box.appendChild(card);
                    });
                });
        }
        farmSel.addEventListener('change', loadWorkers);
        loadWorkers();

        // 선택 사진 미리보기
        input.addEventListener('change', function () {
            var box = document.getElementById('fv-preview'); box.innerHTML = '';
            [].forEach.call(input.files, function (f) {
                if (!f.type.startsWith('image/')) return;
                var img = document.createElement('img'); img.src = URL.createObjectURL(f); box.appendChild(img);
            });
        });

        document.getElementById('fv-save').addEventListener('click', function () {
            var farm = document.getElementById('fv-farm').value;
            var date = document.getElementById('fv-date').value;
            if (!farm) { ndnToast('농가를 선택하세요.', { type: 'error' }); return; }
            if (!date) { ndnToast('방문일을 입력하세요.', { type: 'error' }); return; }

            var fd = new FormData();
            fd.append('farm_id', farm);
            fd.append('visited_on', date);
            fd.append('farm_status', document.getElementById('fv-farm-status').value);
            fd.append('worker_status', document.getElementById('fv-worker-status').value);
            var hc = document.getElementById('fv-headcount').value; if (hc) fd.append('worker_headcount', hc);
            ['work','issue','action','memo'].forEach(function (k) {
                var v = document.getElementById('fv-' + k).value.trim();
                if (v) fd.append(k === 'work' ? 'work_note' : k === 'issue' ? 'issue_note' : k === 'action' ? 'action_note' : 'memo', v);
            });
            [].forEach.call(input.files, function (f) { fd.append('photos[]', f); });

            // 근로자별 인터뷰 (체크=양호=1, 미체크=이상=0)
            [].forEach.call(document.querySelectorAll('#fv-workers .fv-worker'), function (card) {
                var wid = card.getAttribute('data-wid');
                [].forEach.call(card.querySelectorAll('input[data-item]'), function (chk) {
                    fd.append('interviews[' + wid + '][' + chk.getAttribute('data-item') + ']', chk.checked ? '1' : '0');
                });
                fd.append('interviews[' + wid + '][memo]', card.querySelector('.fv-worker__memo').value.trim());
            });

            var btn = document.getElementById('fv-save'); btn.disabled = true; btn.textContent = '저장 중…';
            fetch(BASE, { method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: fd })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok) {
                        var msg = res.j.message || (res.j.errors ? Object.values(res.j.errors)[0][0] : '저장 실패');
                        ndnToast(msg, { type: 'error' }); btn.disabled = false; btn.textContent = '방문 점검 저장'; return;
                    }
                    ndnToast('방문 점검이 등록되었습니다.', { type: 'success' });
                    setTimeout(function () { location.reload(); }, 900);
                })
                .catch(function () { ndnToast('저장 실패', { type: 'error' }); btn.disabled = false; btn.textContent = '방문 점검 저장'; });
        });

        // 상세 모달
        function riskCls(v) { return 'fv-risk--' + (v || 'low'); }
        function renderIv(iv) {
            var items = iv.items.map(function (it) {
                return '<span class="' + (it.ok ? 'fv-tag-ok' : 'fv-tag-bad') + '">' + esc(it.label) + '</span>';
            }).join('');
            return '<div class="fv-iv">'
                + '<div class="fv-iv__head"><span class="fv-iv__worker">' + esc(iv.worker) + '</span>'
                + '<span class="fv-iv__right"><span class="fv-risk ' + riskCls(iv.risk_level) + '">이탈 ' + esc(iv.risk) + '</span>'
                + '<button type="button" class="fv-histbtn" data-hist="' + iv.worker_id + '">이력</button></span></div>'
                + '<div class="fv-iv__items">' + items + '</div>'
                + (iv.memo ? '<div class="fv-iv__memo">' + esc(iv.memo) + '</div>' : '')
                + '<div class="fv-hist" data-histbox="' + iv.worker_id + '" style="display:none"></div>'
                + '</div>';
        }
        function loadHistory(wid, box) {
            if (box.getAttribute('data-loaded')) { box.style.display = (box.style.display === 'none') ? '' : 'none'; return; }
            box.style.display = ''; box.innerHTML = '<div class="fv-hist__row">불러오는 중…</div>';
            fetch(BASE + '/workers/' + wid + '/interviews', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    var rows = (j.history || []).map(function (h) {
                        var neg = h.items.filter(function (i) { return !i.ok; }).length;
                        return '<div class="fv-hist__row"><b>' + esc(h.date) + '</b>'
                            + '<span class="fv-risk ' + riskCls(h.risk_level) + '">' + esc(h.risk) + '</span>'
                            + '<span>' + esc(h.source) + (h.farm ? ' · ' + esc(h.farm) : '') + '</span>'
                            + (neg ? '<span>이상 ' + neg + '항목</span>' : '<span>전항목 양호</span>') + '</div>';
                    }).join('') || '<div class="fv-hist__row">이력이 없습니다.</div>';
                    box.innerHTML = rows; box.setAttribute('data-loaded', '1');
                });
        }
        function openDetail(id) {
            fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    var rows = [
                        ['농가', d.farm], ['방문일', d.visited_on], ['점검자', d.inspector],
                        ['농가 상태', d.farm_status], ['근무 상태', d.worker_status],
                        ['재직 인원', d.headcount != null ? d.headcount + '명' : '—'],
                        ['근무 현황', d.work_note || '—'], ['애로사항', d.issue_note || '—'],
                        ['조치·후속', d.action_note || '—'], ['메모', d.memo || '—'],
                    ];
                    var html = '<div class="fv-detail-head"><b>' + esc(d.farm) + ' · ' + esc(d.visited_on) + '</b>'
                        + '<button type="button" class="fv-back" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></div>';
                    html += '<dl class="fv-dl">';
                    rows.forEach(function (r) { html += '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>'; });
                    html += '</dl>';
                    if (d.photos && d.photos.length) {
                        html += '<div class="fv-gallery">';
                        d.photos.forEach(function (p) { html += '<a href="' + p.url + '" target="_blank"><img src="' + p.url + '" alt="' + esc(p.name) + '" loading="lazy"></a>'; });
                        html += '</div>';
                    }
                    if (d.interviews && d.interviews.length) {
                        html += '<div class="fv-ivs"><div class="fv-ivs__title">근로자 인터뷰 (' + d.interviews.length + '명)</div>';
                        d.interviews.forEach(function (iv) { html += renderIv(iv); });
                        html += '</div>';
                    }
                    document.getElementById('fv-detail').innerHTML = html;
                    document.getElementById('fv-detail-tab').hidden = false;
                    window.ndnSwitchTab('detail');
                });
        }
        document.getElementById('fv-table').addEventListener('dblclick', function (e) {
            var tr = e.target.closest('tr[data-id]'); if (tr) openDetail(tr.getAttribute('data-id'));
        });
        // 근로자 인터뷰 '이력' 토글 (상세 탭 위임)
        document.getElementById('fv-detail').addEventListener('click', function (e) {
            var b = e.target.closest('[data-hist]'); if (!b) return;
            var box = document.querySelector('[data-histbox="' + b.getAttribute('data-hist') + '"]');
            if (box) loadHistory(b.getAttribute('data-hist'), box);
        });
    })();
</script>
@endsection
