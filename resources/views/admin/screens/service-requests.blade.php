@extends('admin.screens.layout')
@section('title', 'SR')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">SR · 서비스 요청</h1>
            <p class="screen__sub">시스템 개선·오류를 요청하고 담당자가 답글로 처리합니다 · <strong>적용 완료</strong>로 바꾸면 등록자에게 이메일이 발송됩니다</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">목록</button>
        <button type="button" class="screen-tab" data-tab="new">새 SR 등록</button>
        <button type="button" class="screen-tab" data-tab="detail" id="sr-detail-tab" hidden>상세</button>
    </div>

    <div data-tabpane="list">
            <div id="grid-service-requests"></div>
    </div>

    <div data-tabpane="new" hidden>
        <div class="sr-form">
            <div class="sr-field">
                <label for="sr-title">제목</label>
                <input type="text" id="sr-title" maxlength="200" placeholder="예: 근로자 목록에서 지역별 필터가 필요합니다">
            </div>
            <div class="sr-field">
                <label for="sr-body">내용</label>
                <textarea id="sr-body" rows="8" maxlength="5000" placeholder="어떤 화면에서 무엇이 필요한지, 재현 방법이 있다면 함께 적어 주세요."></textarea>
            </div>
            <div class="sr-form__foot">
                <button type="button" class="sr-btn sr-btn--primary" id="sr-submit">SR 등록</button>
            </div>
        </div>
    </div>

    <div data-tabpane="detail" hidden>
        <div id="sr-detail" class="dtl"><div class="dtl-empty">목록에서 SR 을 클릭하면 상세가 표시됩니다.</div></div>
    </div>

    <style>
        .sr-badge{display:inline-block;padding:2px 9px;border-radius:100px;font-size:12px;font-weight:700;white-space:nowrap;}
        .sr-badge--received{background:#FFF3E0;color:#B45309;}
        .sr-badge--in_progress{background:#E8F0FE;color:#1A4FA0;}
        .sr-badge--completed{background:#E7F3F1;color:#12695F;}
        .sr-badge--rejected{background:#FDECEC;color:#B42318;}
        .sr-form{max-width:760px;background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:20px;}
        .sr-field{margin-bottom:16px;}
        .sr-field label{display:block;font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);margin-bottom:6px;}
        .sr-field input,.sr-field textarea{width:100%;font-family:inherit;font-size:var(--mv2-fz-sm);padding:9px 11px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);resize:vertical;}
        .sr-form__foot{text-align:right;}
        .sr-btn{font-family:inherit;font-size:var(--mv2-fz-xs);font-weight:700;border:1px solid var(--mv2-border-default);background:#fff;border-radius:var(--mv2-r-sm);padding:6px 14px;cursor:pointer;margin-left:6px;}
        .sr-btn--primary{background:var(--mv2-primary-500);color:#fff;border-color:transparent;}
        .sr-btn--primary:hover{background:var(--mv2-primary-600);}
        .sr-body{white-space:pre-wrap;background:var(--mv2-slate-25);border-radius:var(--mv2-r-sm);padding:12px 14px;font-size:var(--mv2-fz-sm);line-height:1.6;}
        .sr-reply{border-top:1px solid var(--mv2-border-soft);padding:11px 2px;}
        .sr-reply__head{font-size:var(--mv2-fz-xs);color:var(--mv2-text-muted);margin-bottom:4px;}
        .sr-reply__head b{color:var(--mv2-text-strong);}
        .sr-reply__body{white-space:pre-wrap;font-size:var(--mv2-fz-sm);line-height:1.6;}
        .sr-actions{display:flex;flex-wrap:wrap;gap:6px;justify-content:flex-end;margin-top:10px;}
    </style>
@endsection

@section('wwgrid')
<script>
    // SR 은 등록한 사람과 담당자가 주고받은 기록이다. 제목·본문을 표에서 고칠 수
    // 있으면 무엇을 요청했는지가 흔들리므로 **읽기 전용**으로 둔다. 상태 변경과
    // 답글은 상세에서 한다.
    window.srGrid = wwConsole({
        el: 'grid-service-requests',
        title: 'SR',
        data: @json($rows, JSON_UNESCAPED_UNICODE),
        columns: [
            { header: 'SR', name: 'sr_no', width: 70, align: 'center', sortable: true },
            { header: '제목', name: 'title', width: 320, sortable: true },
            { header: '등록자', name: 'requester', width: 110, align: 'center', sortable: true },
            { header: '담당자', name: 'assignee', width: 110, align: 'center', sortable: true },
            { header: '답글', name: 'replies', width: 64, align: 'center', sortable: true },
            { header: '등록일시', name: 'created', width: 150, align: 'center', sortable: true },
            { header: '상태', name: 'status_label', width: 100, align: 'center', sortable: true },
            { header: '상세', name: 'detail', width: 74, align: 'center' },
        ],
    });

    document.getElementById('grid-service-requests').addEventListener('click', function (e) {
        var cell = e.target.closest('[data-col-name="detail"][data-row-index]');
        if (!cell) return;
        var row = window.srGrid.getData()[parseInt(cell.getAttribute('data-row-index'), 10)];
        if (row && row.id) window.srOpenDetail(row.id);
    });
</script>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/service-requests') }}';
        var STATUS_LABELS = @json(collect($statuses)->pluck('label', 'value'));
        var current = null;

        function esc(s) { return (s == null ? '' : String(s)); }

        function badge(status) {
            return '<span class="sr-badge sr-badge--' + esc(status) + '">'
                + esc(STATUS_LABELS[status] || status) + '</span>';
        }

        // 등록·상태 변경 뒤 목록을 다시 채운다. 표는 위쪽 wwgrid 구역에서 만든다.
        function renderRows(rows) {
            if (window.srGrid) window.srGrid.setData(rows || []);
        }

        // ── 상세 ──
        function openDetail(id) {
            fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (d) {
                    current = d.id;
                    var html = '<div class="dtl-head"><b>SR #' + d.id + ' · ' + esc(d.title) + '</b>'
                        + '<div class="dtl-head__actions"><button type="button" class="dtl-back" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></div></div>';

                    html += '<dl class="dtl-dl">'
                        + '<dt>상태</dt><dd>' + badge(d.status) + '</dd>'
                        + '<dt>등록자</dt><dd>' + esc(d.requester || '—') + '</dd>'
                        + '<dt>담당자</dt><dd>' + esc(d.assignee || '—') + '</dd>'
                        + '<dt>등록일시</dt><dd>' + esc(d.created) + '</dd>'
                        + (d.completed ? '<dt>완료일시</dt><dd>' + esc(d.completed) + '</dd>' : '')
                        + '</dl>';

                    html += '<div class="dtl-sec"><div class="dtl-sec__title">요청 내용</div>'
                        + '<div class="sr-body">' + esc(d.body) + '</div></div>';

                    html += '<div class="dtl-sec"><div class="dtl-sec__title">답글 (' + d.replies.length + ')</div>';
                    if (d.replies.length) {
                        d.replies.forEach(function (rp) {
                            html += '<div class="sr-reply"><div class="sr-reply__head"><b>' + esc(rp.author || '—')
                                + '</b> · ' + esc(rp.created) + '</div>'
                                + '<div class="sr-reply__body">' + esc(rp.body) + '</div></div>';
                        });
                    } else {
                        html += '<div class="dtl-empty">아직 답글이 없습니다.</div>';
                    }
                    html += '</div>';

                    html += '<div class="dtl-sec"><div class="dtl-sec__title">답글 달기</div>'
                        + '<div class="sr-field"><textarea id="sr-reply-body" rows="4" maxlength="5000" placeholder="처리 내용·확인 사항을 남겨 주세요."></textarea></div>'
                        + '<div class="sr-actions"><button type="button" class="sr-btn sr-btn--primary" id="sr-reply-send">답글 등록</button></div></div>';

                    if (d.transitions.length) {
                        html += '<div class="dtl-sec"><div class="dtl-sec__title">상태 변경</div><div class="sr-actions">';
                        d.transitions.forEach(function (t) {
                            html += '<button type="button" class="sr-btn' + (t.value === 'completed' ? ' sr-btn--primary' : '')
                                + '" data-status="' + t.value + '">' + esc(t.label) + '</button>';
                        });
                        html += '</div></div>';
                    }

                    document.getElementById('sr-detail').innerHTML = html;
                    document.getElementById('sr-detail-tab').hidden = false;
                    window.ndnSwitchTab('detail');
                })
                .catch(function () { ndnToast('SR 상세를 불러오지 못했습니다.', { type: 'error' }); });
        }

        // 표가 부를 수 있도록 창구만 열어 둔다 — 표는 눌린 순간에야 이걸 찾는다.
        window.srOpenDetail = function (id) { openDetail(id); };

        // ── 등록 ──
        document.getElementById('sr-submit').addEventListener('click', function () {
            var title = document.getElementById('sr-title').value.trim();
            var body = document.getElementById('sr-body').value.trim();
            if (!title || !body) { ndnToast('제목과 내용을 입력하세요.', { type: 'error' }); return; }

            post(BASE, { title: title, body: body }, function (j) {
                document.getElementById('sr-title').value = '';
                document.getElementById('sr-body').value = '';
                renderRows(j.rows);
                window.ndnSwitchTab('list');
            });
        });

        // ── 답글·상태 변경 (상세는 매번 다시 그려지므로 위임으로 처리) ──
        document.getElementById('sr-detail').addEventListener('click', function (e) {
            if (e.target.id === 'sr-reply-send') {
                var body = document.getElementById('sr-reply-body').value.trim();
                if (!body) { ndnToast('답글 내용을 입력하세요.', { type: 'error' }); return; }
                post(BASE + '/' + current + '/replies', { body: body }, function () { openDetail(current); });
                return;
            }

            var stBtn = e.target.closest('button[data-status]');
            if (!stBtn) return;
            var status = stBtn.getAttribute('data-status');
            var isDone = status === 'completed';

            ndnConfirm(
                isDone ? '적용 완료로 변경하면 등록자에게 완료 이메일이 발송됩니다. 진행할까요?'
                       : '상태를 "' + (STATUS_LABELS[status] || status) + '" 로 변경할까요?',
                { title: 'SR 상태 변경', okText: '변경', danger: status === 'rejected' }
            ).then(function (ok) {
                if (!ok) return;
                post(BASE + '/' + current + '/status', { status: status }, function (j) {
                    if (j.rows) renderRows(j.rows);
                    openDetail(current);
                });
            });
        });

        function post(url, payload, onOk) {
            fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(payload),
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j.ok !== false) {
                        ndnToast(res.j.message || '처리했습니다.', { type: 'success' });
                        onOk(res.j);
                    } else {
                        ndnToast(res.j.message || '처리하지 못했습니다.', { type: 'error' });
                    }
                })
                .catch(function () { ndnToast('요청에 실패했습니다.', { type: 'error' }); });
        }
    })();
</script>
@endsection
