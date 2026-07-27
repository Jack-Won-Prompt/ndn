@extends('admin.screens.layout')
@section('title', '농가 방문 점검')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">농가 방문 점검</h1>
            <p class="screen__sub">본사 월별 농가 방문 · 농가 상태·근로자 근무 현황·애로사항 등록 + 현장 사진 여러 장 업로드(방문 증빙) · 위치정보 미저장(§7-2)</p>
        </div>
    </div>

    {{-- 등록 폼 --}}
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
        </div>
        <div class="fv-actions">
            <button type="button" id="fv-save" class="fv-btn">방문 점검 저장</button>
        </div>
    </div>

    {{-- 목록 --}}
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

    {{-- 상세 모달 --}}
    <div class="fv-modal" id="fv-modal">
        <div class="fv-modal__card">
            <div class="fv-modal__head">
                <b id="fv-modal-title">방문 점검</b>
                <button type="button" class="fv-modal__x" id="fv-modal-close">&times;</button>
            </div>
            <div class="fv-modal__body" id="fv-modal-body"></div>
        </div>
    </div>

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
        .fv-modal{position:fixed;inset:0;background:rgba(15,23,42,.4);display:none;align-items:center;justify-content:center;z-index:1000;}
        .fv-modal.is-open{display:flex;}
        .fv-modal__card{width:640px;max-width:94%;max-height:88vh;background:#fff;border-radius:14px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.3);}
        .fv-modal__head{display:flex;justify-content:space-between;align-items:center;padding:14px 18px;border-bottom:1px solid var(--mv2-border-soft);}
        .fv-modal__x{background:none;border:0;font-size:22px;cursor:pointer;color:var(--mv2-text-muted);}
        .fv-modal__body{padding:18px;overflow-y:auto;}
        .fv-dl{display:grid;grid-template-columns:120px 1fr;gap:0;margin:0;}
        .fv-dl dt{color:var(--mv2-text-muted);font-size:var(--mv2-fz-xs);font-weight:700;padding:8px 0;border-bottom:1px solid var(--mv2-border-soft);}
        .fv-dl dd{margin:0;font-size:var(--mv2-fz-sm);padding:8px 0;border-bottom:1px solid var(--mv2-border-soft);white-space:pre-wrap;}
        .fv-gallery{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px;}
        .fv-gallery a{display:block;}
        .fv-gallery img{width:120px;height:120px;object-fit:cover;border-radius:10px;border:1px solid var(--mv2-border-soft);}
        @media (max-width:820px){.fv-grid{grid-template-columns:1fr;}}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/farm-visits') }}';
        var input = document.getElementById('fv-photos');

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
        function esc(s) { return (s == null ? '' : String(s)); }
        function openDetail(id) {
            fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    document.getElementById('fv-modal-title').textContent = d.farm + ' · ' + d.visited_on;
                    var rows = [
                        ['농가', d.farm], ['방문일', d.visited_on], ['점검자', d.inspector],
                        ['농가 상태', d.farm_status], ['근무 상태', d.worker_status],
                        ['재직 인원', d.headcount != null ? d.headcount + '명' : '—'],
                        ['근무 현황', d.work_note || '—'], ['애로사항', d.issue_note || '—'],
                        ['조치·후속', d.action_note || '—'], ['메모', d.memo || '—'],
                    ];
                    var html = '<dl class="fv-dl">';
                    rows.forEach(function (r) { html += '<dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd>'; });
                    html += '</dl>';
                    if (d.photos && d.photos.length) {
                        html += '<div class="fv-gallery">';
                        d.photos.forEach(function (p) { html += '<a href="' + p.url + '" target="_blank"><img src="' + p.url + '" alt="' + esc(p.name) + '" loading="lazy"></a>'; });
                        html += '</div>';
                    }
                    document.getElementById('fv-modal-body').innerHTML = html;
                    document.getElementById('fv-modal').classList.add('is-open');
                });
        }
        document.getElementById('fv-table').addEventListener('dblclick', function (e) {
            var tr = e.target.closest('tr[data-id]'); if (tr) openDetail(tr.getAttribute('data-id'));
        });
        document.getElementById('fv-modal-close').addEventListener('click', function () { document.getElementById('fv-modal').classList.remove('is-open'); });
        document.getElementById('fv-modal').addEventListener('click', function (e) { if (e.target.id === 'fv-modal') this.classList.remove('is-open'); });
    })();
</script>
@endsection
