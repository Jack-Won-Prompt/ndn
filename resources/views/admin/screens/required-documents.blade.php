@extends('admin.screens.layout')
@section('title', '필수 동의 문서')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">필수 확인·동의 문서</h1>
            <p class="screen__sub">근로자가 <strong>모두 동의해야 앱을 계속 사용</strong>할 수 있는 문서입니다 · 본문은 언어별로 입력 · 문안이 바뀌면 <strong>새 버전으로 저장</strong>해 전원에게 다시 받으세요</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">문서 목록</button>
        <button type="button" class="screen-tab" data-tab="edit" id="rd-edit-tab" hidden>본문 편집</button>
    </div>

    <div data-tabpane="list">
        <div id="grid-required-documents"></div>
        <p class="rd-listhint">
            <strong>[본문 편집 ▸]</strong> 칸을 누르면 언어별 본문 편집기가 열립니다.
            <strong>[내려받기 ▸]</strong> 는 원본 서식이 붙은 문서에만 뜹니다.
            <br>문안이 바뀌면 <strong>새 버전으로 저장</strong>해야 이미 동의한 사람도 다시 받습니다 —
            같은 버전에서 글자만 고치면 동의 이력이 실제 문안과 어긋납니다.
        </p>
    </div>

    <div data-tabpane="edit" hidden>
        <div id="rd-edit" class="dtl"><div class="dtl-empty">목록에서 <b>[본문 편집 ▸]</b> 칸을 누르면 본문 편집기가 열립니다.</div></div>
    </div>

    <style>
        .rd-listhint{font-size:var(--mv2-fz-xs);color:var(--mv2-text-faint);margin:10px 2px 0;line-height:1.7;}
        .rd-code{color:var(--mv2-text-faint);font-size:var(--mv2-fz-xs);margin-left:6px;}
        .rd-loc{display:inline-block;min-width:22px;padding:1px 5px;margin:0 1px;border-radius:4px;font-size:11px;font-weight:700;}
        .rd-loc--on{background:#E7F3F1;color:#12695F;}
        .rd-loc--off{background:#F1F3F7;color:#9AA3B2;}
        .rd-badge{display:inline-block;padding:2px 9px;border-radius:100px;font-size:12px;font-weight:700;}
        .rd-badge--on{background:#E7F3F1;color:#12695F;}
        .rd-badge--off{background:#F1F3F7;color:#6B7280;}
        .rd-file{display:inline-block;padding:3px 11px;border-radius:100px;font-size:12.5px;font-weight:700;
            background:var(--mv2-slate-25);border:1px solid var(--mv2-border-default);color:var(--mv2-text-strong);text-decoration:none;}
        .rd-file:hover{border-color:var(--mv2-text-strong);}
        .rd-nofile{color:var(--mv2-text-faint);}
        .rd-fileline{margin:14px 0 4px;padding:12px 14px;border-radius:var(--mv2-r-sm);
            background:var(--mv2-slate-25);font-size:13.5px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
        .rd-filehelp{margin:0 0 6px;font-size:12.5px;line-height:1.75;color:var(--mv2-text-muted);}
        .rd-filehelp b{color:var(--mv2-text-strong);}
        .rd-langtabs{display:flex;gap:4px;margin-bottom:12px;flex-wrap:wrap;}
        .rd-langtab{font-family:inherit;font-size:var(--mv2-fz-xs);font-weight:700;padding:5px 12px;border:1px solid var(--mv2-border-default);background:#fff;border-radius:100px;cursor:pointer;}
        .rd-langtab.is-active{background:var(--mv2-ink,#0F172A);color:#fff;border-color:transparent;}
        .rd-pane label{display:block;font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);margin:10px 0 5px;}
        .rd-pane input,.rd-pane textarea{width:100%;font-family:inherit;font-size:var(--mv2-fz-sm);padding:9px 11px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);resize:vertical;}
        .rd-opts{display:flex;gap:18px;align-items:center;margin-top:14px;font-size:var(--mv2-fz-sm);}
        .rd-opts label{display:flex;align-items:center;gap:6px;cursor:pointer;}
        .rd-foot{display:flex;justify-content:flex-end;gap:8px;margin-top:16px;}
        .rd-btn{font-family:inherit;font-size:var(--mv2-fz-xs);font-weight:700;border:1px solid var(--mv2-border-default);background:#fff;border-radius:var(--mv2-r-sm);padding:7px 15px;cursor:pointer;}
        .rd-btn--primary{background:var(--mv2-primary-500);color:#fff;border-color:transparent;}
    </style>
@endsection

@section('wwgrid')
<script>
    // 문서 목록은 **읽기 전용**이다. 제목·사용 여부를 표에서 고칠 수 있게 하면
    // 본문과 따로 놀게 된다 — 문안과 메타는 같은 편집기에서 함께 저장해야 한다.
    var rdGrid = wwConsole({
        el: 'grid-required-documents',
        title: '필수동의문서',
        data: @json($rows, JSON_UNESCAPED_UNICODE),
        columns: [
            { header: '문서', name: 'title_label', width: 280, sortable: true },
            { header: '버전', name: 'version_label', width: 70, align: 'center' },
            // 근로자 대상 문서는 언어가 다 차 있어야 한다(§6).
            { header: '번역', name: 'locales_label', width: 240 },
            { header: '원본 서식', name: 'file_label', width: 110, align: 'center' },
            { header: '동의 필수', name: 'required_label', width: 90, align: 'center', sortable: true },
            { header: '사용', name: 'active_label', width: 80, align: 'center', sortable: true },
            { header: '동의 인원', name: 'agreed', width: 90, align: 'center', sortable: true },
            { header: '수정일시', name: 'updated', width: 150, align: 'center', sortable: true },
            { header: '본문', name: 'edit', width: 110, align: 'center' },
        ],
    });

    document.getElementById('grid-required-documents').addEventListener('click', function (e) {
        var cell = e.target.closest('[data-col-name][data-row-index]');
        if (!cell) return;

        var row = rdGrid.getData()[parseInt(cell.getAttribute('data-row-index'), 10)];
        if (!row) return;

        var col = cell.getAttribute('data-col-name');
        if (col === 'edit') { window.rdOpenEdit(row.id); return; }
        // 원본 서식은 새 창으로 받는다 — 편집기를 열면서 받으면 둘 다 어정쩡해진다.
        if (col === 'file' || col === 'file_label') {
            if (row.file_url) window.open(row.file_url, '_blank', 'noopener');
        }
    });
</script>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/required-documents') }}';
        var LOCALES = @json(\App\Domains\Onboarding\Models\RequiredDocument::LOCALES);
        var LOCALE_NAMES = { ko: '한국어', bn: '벵골어', lo: '라오어', si: '싱할라어', vi: '베트남어', ne: '네팔어', ky: '키르기스어' };
        var doc = null;

        function esc(s) { return (s == null ? '' : String(s)); }

        function openDoc(id) {
            fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (d) {
                    doc = d;
                    var html = '<div class="dtl-head"><b>' + esc(d.code) + ' · v' + d.version + '</b>'
                        + '<div class="dtl-head__actions"><button type="button" class="dtl-back" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></div></div>';

                    // 원본이 붙은 문서는 본문을 옮겨 적지 않는다 — 파일을 받아 읽는다.
                    html += '<div class="rd-fileline"><b>원본 서식</b>';
                    if (d.file_url) {
                        html += '<span>' + esc(d.file) + '</span>'
                            + '<a class="rd-file" href="' + d.file_url + '">내려받기</a>'
                            + '<button type="button" class="rd-btn" id="rd-file-remove">떼기</button>';
                    } else {
                        html += '<span style="color:var(--mv2-text-faint)">붙어 있지 않음</span>';
                    }
                    html += '<input type="file" id="rd-file-input" accept=".pdf,.doc,.docx,.hwp,.hwpx" hidden>'
                        + '<button type="button" class="rd-btn" id="rd-file-pick">'
                        + (d.file_url ? '다른 파일로 바꾸기' : '원본 올리기') + '</button>'
                        + '</div>'
                        + '<p class="rd-filehelp">'
                        + '법적 서식은 <b>옮겨 적지 말고 원본을 올리십시오.</b> 손으로 옮기면 문안이 원본과 달라집니다.<br>'
                        + '원본을 붙이면 <b>본문을 비워 둔 채로도 문서를 켤 수 있습니다.</b> '
                        + '근로자에게는 각자 언어의 파일명으로 내려갑니다. (PDF·DOC·DOCX·HWP, 20MB 이하)'
                        + '</p>';

                    html += '<div class="rd-langtabs">';
                    LOCALES.forEach(function (l, i) {
                        html += '<button type="button" class="rd-langtab' + (i === 0 ? ' is-active' : '') + '" data-loc="' + l + '">'
                            + LOCALE_NAMES[l] + '</button>';
                    });
                    html += '</div>';

                    LOCALES.forEach(function (l, i) {
                        html += '<div class="rd-pane" data-pane="' + l + '"' + (i === 0 ? '' : ' hidden') + '>'
                            + '<label>제목 (' + LOCALE_NAMES[l] + ')</label>'
                            + '<input type="text" maxlength="200" data-field="title" data-loc="' + l + '" value="' + esc(d.locales[l].title).replace(/"/g, '&quot;') + '">'
                            + '<label>본문 (' + LOCALE_NAMES[l] + ')</label>'
                            + '<textarea rows="16" data-field="body" data-loc="' + l + '">' + esc(d.locales[l].body) + '</textarea>'
                            + '</div>';
                    });

                    html += '<div class="rd-opts">'
                        + '<label><input type="checkbox" id="rd-required"' + (d.required ? ' checked' : '') + '> 동의 필수</label>'
                        + '<label><input type="checkbox" id="rd-active"' + (d.active ? ' checked' : '') + '> 근로자에게 노출(사용)</label>'
                        + '<label><input type="checkbox" id="rd-bump"> 새 버전으로 저장 — 전원 재동의</label>'
                        + '</div>';

                    html += '<div class="rd-foot"><button type="button" class="rd-btn rd-btn--primary" id="rd-save">저장</button></div>';

                    document.getElementById('rd-edit').innerHTML = html;
                    document.getElementById('rd-edit-tab').hidden = false;
                    window.ndnSwitchTab('edit');
                })
                .catch(function () { ndnToast('문서를 불러오지 못했습니다.', { type: 'error' }); });
        }

        // 표는 위쪽 wwgrid 구역에서 만든다(그쪽이 먼저 실행된다). 표가 부를 수
        // 있도록 창구만 열어 둔다.
        window.rdOpenEdit = function (id) { openDoc(id); };

        // 원본 서식 올리기·떼기
        document.getElementById('rd-edit').addEventListener('change', function (e) {
            if (e.target.id !== 'rd-file-input' || !e.target.files.length) return;

            var fd = new FormData();
            fd.append('file', e.target.files[0]);

            fetch(BASE + '/' + doc.id + '/file', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: fd,
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j.ok) {
                        ndnToast(res.j.message, { type: 'success' });
                        window.location.reload();
                    } else {
                        var m = res.j.message
                            || (res.j.errors ? Object.values(res.j.errors)[0][0] : '올리지 못했습니다.');
                        ndnToast(m, { type: 'error' });
                    }
                })
                .catch(function () { ndnToast('올리지 못했습니다.', { type: 'error' }); });
        });

        document.getElementById('rd-edit').addEventListener('click', function (e) {
            if (e.target.id === 'rd-file-pick') {
                document.getElementById('rd-file-input').click();
                return;
            }

            if (e.target.id === 'rd-file-remove') {
                ndnConfirm('붙여 둔 원본 서식을 뗍니다. 파일 자체는 남습니다.',
                    { title: '원본 떼기', okText: '떼기', danger: true }).then(function (ok) {
                        if (!ok) return;
                        fetch(BASE + '/' + doc.id + '/file', {
                            method: 'DELETE',
                            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                        })
                            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                            .then(function (res) {
                                if (res.ok && res.j.ok) {
                                    ndnToast(res.j.message, { type: 'success' });
                                    window.location.reload();
                                } else {
                                    ndnToast(res.j.message || '떼지 못했습니다.', { type: 'error' });
                                }
                            });
                    });
                return;
            }

            var lang = e.target.closest('.rd-langtab');
            if (lang) {
                var loc = lang.getAttribute('data-loc');
                document.querySelectorAll('.rd-langtab').forEach(function (b) { b.classList.toggle('is-active', b === lang); });
                document.querySelectorAll('.rd-pane').forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== loc; });
                return;
            }

            if (e.target.id !== 'rd-save') return;

            var locales = {};
            LOCALES.forEach(function (l) {
                locales[l] = {
                    title: document.querySelector('[data-field="title"][data-loc="' + l + '"]').value,
                    body: document.querySelector('[data-field="body"][data-loc="' + l + '"]').value,
                };
            });

            var payload = {
                locales: locales,
                required: document.getElementById('rd-required').checked,
                active: document.getElementById('rd-active').checked,
                bump_version: document.getElementById('rd-bump').checked,
            };

            var go = payload.bump_version
                ? ndnConfirm('새 버전으로 저장하면 이미 동의한 근로자 전원이 다시 동의해야 합니다. 진행할까요?',
                    { title: '새 버전으로 저장', okText: '저장', danger: true })
                : Promise.resolve(true);

            go.then(function (ok) {
                if (!ok) return;
                fetch(BASE + '/' + doc.id, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(payload),
                })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (res) {
                        if (res.ok && res.j.ok) {
                            ndnToast(res.j.message, { type: 'success' });
                            window.location.reload();
                        } else {
                            ndnToast(res.j.message || '저장하지 못했습니다.', { type: 'error' });
                        }
                    });
            });
        });
    })();
</script>
@endsection
