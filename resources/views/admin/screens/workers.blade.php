@extends('admin.screens.layout')
@section('title', '근로자')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">근로자</h1>
            <p class="screen__sub">
                <strong>편집 후 [변경 저장]</strong> · <strong>번호 열 더블클릭</strong>으로 상세(입국·생활점검 이력) ·
                엑셀 업로드는 <strong>번호 또는 여권번호가 같으면 수정</strong>, 없으면 새로 등록합니다 ·
                여권번호·생년월일·연락처·이메일이 함께 보이며 <strong>열람 기록이 남습니다(§7-6)</strong>
            </p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">목록</button>
        <button type="button" class="screen-tab" data-tab="detail" id="wk-detail-tab">상세</button>
    </div>

    <div data-tabpane="list">
        {{-- 지자체가 주는 명단에는 한 시군·한 나라 사람만 실려 있어 국적·지역 칸을
             아예 안 적어 온다. 파일에 그 칸이 없을 때만 여기 고른 값이 쓰인다. --}}
        <div class="wk-imp">
            <span class="wk-imp__t">엑셀 업로드 기본값</span>
            <label>국적
                <select id="imp-nat">
                    @foreach (App\Domains\Recruitment\Enums\Nationality::adminOptions() as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>지역
                <select id="imp-city">
                    <option value="">지정 안 함</option>
                    @foreach ($cityOptions as $c)
                        <option value="{{ $c['value'] }}">{{ $c['label'] }}</option>
                    @endforeach
                </select>
            </label>
            <label>시트
                <input id="imp-sheet" type="text" placeholder="비우면 첫 시트" value="근로자 리스트">
            </label>
            <span class="wk-imp__h">파일에 해당 칸이 있으면 파일 값이 우선합니다.</span>
        </div>
        <div id="grid-workers"></div>
    </div>
    <div data-tabpane="detail" hidden>
        <div id="wk-detail" class="dtl"><div class="dtl-empty">목록에서 <b>번호 열</b>을 더블클릭하면 상세(입국·점검·개인 서류)가 표시됩니다.</div></div>
    </div>

    <style>
        /* 엑셀 업로드 기본값 줄 — 표 바로 위, 툴바와 한 덩어리로 보이게 */
        .wk-imp { display: flex; align-items: center; flex-wrap: wrap; gap: 12px;
            padding: 10px 12px; margin-bottom: 10px;
            background: var(--mv2-slate-25); border: 1px solid var(--mv2-border-soft);
            border-radius: var(--mv2-r-sm); font-size: var(--mv2-fz-xs); }
        .wk-imp__t { font-weight: 800; color: var(--mv2-text-strong); }
        .wk-imp label { display: flex; align-items: center; gap: 6px; color: var(--mv2-text-muted); font-weight: 700; }
        .wk-imp select, .wk-imp input { font-family: inherit; font-size: var(--mv2-fz-xs);
            padding: 4px 8px; border: 1px solid var(--mv2-border-default);
            border-radius: var(--mv2-r-sm); background: #fff; color: var(--mv2-text-strong); }
        .wk-imp input { width: 140px; }
        .wk-imp__h { margin-left: auto; color: var(--mv2-text-faint); }
        .wf-row { display: flex; align-items: center; flex-wrap: wrap; gap: 8px;
            padding: 9px 0; border-bottom: 1px solid var(--mv2-border-soft); font-size: var(--mv2-fz-sm); }
        .wf-row:last-child { border-bottom: 0; }
        .wf-type { font-weight: 700; min-width: 110px; color: var(--mv2-text-strong); }
        .wf-name { flex: 1; min-width: 160px; word-break: break-all; }
        .wf-meta { font-size: var(--mv2-fz-xs); color: var(--mv2-text-muted); }
        .wf-note { flex-basis: 100%; font-size: var(--mv2-fz-xs); color: var(--mv2-text-muted); padding-left: 118px; }
        .wf-flag { font-size: 11px; font-weight: 800; border-radius: 100px; padding: 2px 9px; }
        .wf-flag--bad { background: var(--mv2-pill-err-bg); color: var(--mv2-pill-err-fg); }
        .wf-flag--warn { background: #FFF4E0; color: #8A5A00; }
        .wf-form { display: flex; flex-wrap: wrap; gap: 8px; align-items: center;
            margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--mv2-border-soft); }
        .wf-form select, .wf-form input[type=text], .wf-form input[type=date] {
            font-family: inherit; font-size: var(--mv2-fz-sm); padding: 7px 10px;
            border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-sm); background: #fff; }
        .wf-form input[type=text] { flex: 1; min-width: 160px; }
        .wf-btn { font-family: inherit; font-size: var(--mv2-fz-xs); font-weight: 700; text-decoration: none;
            border: 1px solid var(--mv2-border-default); background: #fff; color: var(--mv2-text-strong);
            border-radius: var(--mv2-r-sm); padding: 6px 13px; cursor: pointer; white-space: nowrap; }
        .wf-btn:hover { border-color: var(--mv2-text-strong); }
        .wf-btn--primary { background: var(--mv2-primary-500); color: #fff; border-color: transparent; }
        .wf-btn--danger:hover { border-color: var(--mv2-pill-err-fg); color: var(--mv2-pill-err-fg); }
        .wf-help { font-size: 12px; color: var(--mv2-text-muted); margin: 10px 0 0; line-height: 1.7; }
    </style>
@endsection

@section('wwgrid')
<script>
    function wkEsc(s) { return (s == null ? '' : String(s)); }

    /* ---------- 개인 서류 ---------- */
    var wfWorkerId = null;
    var wfUploadUrl = null;

    function wfRender(files) {
        var box = document.getElementById('wf-list');
        if (!box) return;

        // 올리거나 지운 뒤에도 머리글 건수가 맞아야 한다.
        var count = document.getElementById('wf-count');
        if (count) count.textContent = (files || []).length;

        if (!files || !files.length) {
            box.innerHTML = '<div class="dtl-empty">보관된 서류가 없습니다.</div>';
            return;
        }

        box.innerHTML = files.map(function (f) {
            // 만료된 서류는 눈에 띄어야 한다. 비자가 지난 줄 모르면 사고가 난다.
            var flag = f.expired ? '<span class="wf-flag wf-flag--bad">만료</span>'
                : (f.expiring ? '<span class="wf-flag wf-flag--warn">만료 임박</span>' : '');
            var missing = f.missing ? '<span class="wf-flag wf-flag--bad">파일 없음</span>' : '';

            return '<div class="wf-row">'
                + '<span class="wf-type">' + wkEsc(f.type_label) + '</span>'
                + '<span class="wf-name">' + wkEsc(f.name) + '</span>'
                + '<span class="wf-meta">' + wkEsc(f.size)
                + (f.expires_on ? ' · ~' + wkEsc(f.expires_on) : '') + '</span>'
                + flag + missing
                + '<span class="wf-meta">' + wkEsc(f.uploaded_by) + ' · ' + wkEsc(f.uploaded_at) + '</span>'
                + '<a class="wf-btn" href="' + f.url + '">내려받기</a>'
                + '<button type="button" class="wf-btn wf-btn--danger" data-wf-del="' + f.id + '">삭제</button>'
                + (f.note ? '<div class="wf-note">' + wkEsc(f.note) + '</div>' : '')
                + '</div>';
        }).join('');
    }

    function wfBind(workerId, uploadUrl) {
        wfWorkerId = workerId;
        wfUploadUrl = uploadUrl;

        var btn = document.getElementById('wf-upload');
        if (btn) btn.addEventListener('click', wfUpload);

        var list = document.getElementById('wf-list');
        if (list) {
            list.addEventListener('click', function (e) {
                var del = e.target.closest('[data-wf-del]');
                if (del) wfDelete(del.getAttribute('data-wf-del'));
            });
        }
    }

    function wfUpload() {
        var input = document.getElementById('wf-file');
        if (!input.files.length) { ndnToast('파일을 고르세요.', { type: 'error' }); return; }

        var fd = new FormData();
        fd.append('type', document.getElementById('wf-type').value);
        fd.append('file', input.files[0]);
        var exp = document.getElementById('wf-expires').value;
        if (exp) fd.append('expires_on', exp);
        var note = document.getElementById('wf-note').value.trim();
        if (note) fd.append('note', note);

        var btn = document.getElementById('wf-upload');
        btn.disabled = true; btn.textContent = '올리는 중…';

        fetch(wfUploadUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: fd,
        })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
            .then(function (res) {
                btn.disabled = false; btn.textContent = '올리기';
                if (res.ok && res.j.ok) {
                    ndnToast(res.j.message, { type: 'success' });
                    input.value = '';
                    document.getElementById('wf-note').value = '';
                    document.getElementById('wf-expires').value = '';
                    wfRender(res.j.rows);
                } else {
                    var m = res.j.message || (res.j.errors ? Object.values(res.j.errors)[0][0] : '올리지 못했습니다.');
                    ndnToast(m, { type: 'error' });
                }
            })
            .catch(function () {
                btn.disabled = false; btn.textContent = '올리기';
                ndnToast('올리지 못했습니다.', { type: 'error' });
            });
    }

    function wfDelete(fileId) {
        ndnConfirm('이 서류를 지웁니다. 파일도 함께 삭제됩니다.',
            { title: '서류 삭제', okText: '삭제', danger: true }).then(function (ok) {
                if (!ok) return;
                fetch('{{ url('admin/workers') }}/' + wfWorkerId + '/files/' + fileId, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                })
                    .then(function (r) { return r.json(); })
                    .then(function (j) {
                        if (j.ok) { ndnToast(j.message, { type: 'success' }); wfRender(j.rows); }
                        else { ndnToast(j.message || '지우지 못했습니다.', { type: 'error' }); }
                    });
            });
    }

    function openWorker(id) {
        fetch('{{ url('admin/screen/workers') }}/' + id + '?format=json', { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
            .then(function (d) {
                var html = '<div class="dtl-head"><b>' + wkEsc(d.name) + ' · ' + wkEsc(d.nationality) + '</b>'
                    + '<div class="dtl-head__actions"><button type="button" class="dtl-back" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></div></div>';
                html += '<dl class="dtl-dl">'
                    + '<dt>이름</dt><dd>' + wkEsc(d.name) + '</dd>'
                    + '<dt>국적</dt><dd>' + wkEsc(d.nationality) + '</dd>'
                    // 여권번호·생년월일·전화를 그대로 보여 준다. 가려 두면 담당자가
                    // 엑셀·메신저로 옮겨 적게 되고, 그쪽이 훨씬 위험하다. 열람 기록은 남는다(§7-6).
                    + '<dt>여권번호</dt><dd>' + wkEsc(d.passport_no || '—') + '</dd>'
                    + '<dt>생년월일</dt><dd>' + wkEsc(d.birth_date || '—')
                    + (d.age != null ? ' (만 ' + d.age + '세)' : '') + '</dd>'
                    + '<dt>성별</dt><dd>' + wkEsc(d.gender || '—') + '</dd>'
                    + '<dt>본국 전화</dt><dd>' + wkEsc(d.phone_home_country || '—') + '</dd>'
                    + '<dt>언어</dt><dd>' + wkEsc(d.locale) + '</dd>'
                    + '<dt>상태</dt><dd>' + wkEsc(d.status) + '</dd>'
                    + '<dt>지원 지역</dt><dd>' + wkEsc(d.applied_city || '—') + '</dd>'
                    + '<dt>배치</dt><dd>' + wkEsc((d.city || '—') + ' · ' + (d.farm || '—')) + '</dd>'
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

                html += '<div class="dtl-sec"><div class="dtl-sec__title">근무상태 점검 이력 (' + (d.reviews || []).length + '건)</div>';
                if (d.reviews && d.reviews.length) {
                    d.reviews.forEach(function (r) {
                        html += '<div class="dtl-hist__row"><b>' + wkEsc(r.date) + '</b>'
                            + '<span class="dtl-badge">' + wkEsc(r.risk) + '</span><span>' + wkEsc(r.type) + '</span>'
                            + '<span>' + wkEsc(r.result) + ' · ' + r.score + '점</span></div>';
                    });
                } else { html += '<div class="dtl-empty">점검 이력이 없습니다.</div>'; }
                html += '</div>';

                // 조기 귀국·이탈 — 계정 상태만 봐서는 왜 그렇게 됐는지 알 수 없다.
                // 이력이 있을 때만 보여 준다. 대부분의 근로자에게는 빈 칸이다.
                if (d.exits && d.exits.length) {
                    html += '<div class="dtl-sec"><div class="dtl-sec__title">조기귀국·이탈 (' + d.exits.length + '건)</div>';
                    d.exits.forEach(function (e) {
                        html += '<div class="dtl-hist__row"><b>' + wkEsc(e.date) + '</b>'
                            + '<span class="dtl-badge">' + wkEsc(e.type) + '</span>'
                            + '<span>' + wkEsc(e.status) + ' · ' + wkEsc(e.reason) + '</span>'
                            + '<span>' + wkEsc(e.label) + (e.decided_by ? ' · 결정 ' + wkEsc(e.decided_by) : '') + '</span>'
                            + '</div>';
                    });
                    html += '</div>';
                }

                // 본사가 보관하는 개인 서류 — 여권 사본·건강검진 등
                html += '<div class="dtl-sec"><div class="dtl-sec__title">개인 서류 '
                    + '(<span id="wf-count">' + (d.files || []).length + '</span>건)</div>'
                    + '<div id="wf-list"></div>'
                    + '<div class="wf-form">'
                    + '  <select id="wf-type">'
                    + Object.keys(d.file_types).map(function (k) {
                        return '<option value="' + k + '">' + wkEsc(d.file_types[k]) + '</option>';
                    }).join('')
                    + '  </select>'
                    + '  <input type="date" id="wf-expires" title="유효기간(선택) — 비자·건강검진처럼 만료가 있는 서류">'
                    + '  <input type="text" id="wf-note" maxlength="300" placeholder="메모(선택)">'
                    + '  <input type="file" id="wf-file" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.hwp,.hwpx">'
                    + '  <button type="button" class="wf-btn wf-btn--primary" id="wf-upload">올리기</button>'
                    + '</div>'
                    + '<p class="wf-help">여권 사본은 그 자체로 민감정보입니다. '
                    + '올린 서류는 관리자만 볼 수 있고, 열면 열람 기록이 남습니다. (20MB 이하)</p>'
                    + '</div>';

                document.getElementById('wk-detail').innerHTML = html;
                wfRender(d.files);
                wfBind(d.id, d.file_upload_url);
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
        // 업로드하는 순간의 선택을 읽는다 — 미리 굳혀 두면 바꿔도 안 먹는다.
        importPayload: function () {
            return {
                default_nationality: document.getElementById('imp-nat').value,
                default_city_id: document.getElementById('imp-city').value,
                sheet: document.getElementById('imp-sheet').value.trim(),
            };
        },
        // 사람을 지우면 배정도 함께 정리된다 — 농가 자리가 실제로 비어야 한다.
        deleteWarning: '삭제하면 그 사람의 배정이 취소되어 농가 자리가 비고, 후보자·온보딩·정착 신청·민원·서류도 함께 정리됩니다.',
        newRow: { nationality: 'BD', city_id: null, locale: 'bn', status: 'active' },
        data: @json($rows),
        columns: [
            { header: '번호', name: 'id', width: 64, align: 'center', sortable: true },
            { header: '이름', name: 'name', width: 160, editor: 'text', sortable: true },
            { header: '국적', name: 'nationality', width: 100, editor: 'combo', align: 'center',
              options: [{value:'BD',label:'방글라'},{value:'LA',label:'라오스'},{value:'LK',label:'스리랑카'},{value:'VN',label:'베트남'}] },
            // 지원 지자체 — 가입 시 근로자가 고른 지역. 이전 가입자는 여기서 채운다.
            { header: '지원 지역', name: 'city_id', width: 150, editor: 'combo', align: 'center',
              options: @json($cityOptions) },
            { header: '언어', name: 'locale', width: 100, editor: 'combo', align: 'center',
              options: [{value:'ko',label:'한국어'},{value:'bn',label:'벵골어'},{value:'lo',label:'라오어'},{value:'si',label:'싱할라어'},{value:'vi',label:'베트남어'},{value:'ne',label:'네팔어'},{value:'ky',label:'키르기스어'}] },
            { header: '상태', name: 'status', width: 110, editor: 'combo', align: 'center',
              options: [{value:'pending',label:'승인 대기'},{value:'active',label:'재직'},{value:'inactive',label:'비활성'},{value:'returned',label:'귀국'},{value:'rejected',label:'가입 거절'}] },
            // 아래 네 칸은 암호화해 보관하는 값이다(§7-1). 화면에 보인다고 DB 가 평문이 되지 않는다.
            // 이 목록을 여는 것 자체가 개인정보 열람이라 서버가 열람 기록을 남긴다(§7-6).
            { header: '연락처', name: 'phone_home_country', width: 140, editor: 'text' },
            { header: '이메일', name: 'email', width: 200, editor: 'text' },
            // 여권번호는 숫자가 섞여도 앞자리 0 이 살아야 해 text 로 둔다.
            { header: '여권번호', name: 'passport_no', width: 130, editor: 'text' },
            { header: '생년월일', name: 'birth_date', width: 120, editor: 'date', align: 'center' },
            { header: '비고', name: 'note', width: 220, editor: 'text' },
        ],
        onRowDblClick: function (row) { if (row.id) openWorker(row.id); },
    });
</script>
@endsection
