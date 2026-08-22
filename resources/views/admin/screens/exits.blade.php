@extends('admin.screens.layout')
@section('title', '조기귀국·이탈')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">조기귀국 · 이탈 관리</h1>
            <p class="screen__sub">계약을 채우지 못하고 빠지는 건을 사유와 함께 남깁니다 · 결정하면 <strong>근로자 상태와 농가 배정이 함께 정리</strong>됩니다 · 위치 추적 미사용(§7-2)</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">사건 목록<span class="screen-tab__badge">{{ collect($rows)->where('open', true)->count() }}</span></button>
        <button type="button" class="screen-tab" data-tab="form">사건 등록</button>
        <button type="button" class="screen-tab" data-tab="detail" id="ex-detail-tab" hidden>상세</button>
    </div>

    {{-- 목록 --}}
    <div data-tabpane="list">
        <div id="grid-exits"></div>
        <p class="ex-hint">
            <strong>[상세 ▸]</strong> 칸을 누르면 상세와 처리 버튼이 열립니다.
            <b>진행 중인 건이 위로, 오래 끌고 있는 것부터</b> 정렬됩니다.
            새 사건은 <strong>[사건 등록]</strong> 탭에서 만듭니다 — 목록에서 바로 만들지 않는 이유는
            유형에 따라 받아야 할 값이 다르기 때문입니다.
        </p>
    </div>

    {{-- 등록 --}}
    <div data-tabpane="form" hidden>
        <div class="ex-form">
            @if (count($pendingTickets))
                <div class="ex-tickets">
                    <div class="ex-tickets__title">앱에서 올라온 조기 귀국 신청 <span class="ex-chip">{{ count($pendingTickets) }}건</span></div>
                    <p class="ex-tickets__help">아직 사건으로 열리지 않은 민원입니다. 클릭하면 아래 폼이 채워집니다.</p>
                    @foreach ($pendingTickets as $t)
                        <button type="button" class="ex-ticket" data-tid="{{ $t['id'] }}" data-wid="{{ $t['worker_id'] }}">
                            <b>{{ $t['worker'] }}</b> · {{ $t['subject'] }} <span class="ex-dim">{{ $t['date'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="ex-grid">
                <div class="ex-field">
                    <label>유형 <em>*</em></label>
                    <select id="ex-type">
                        @foreach ($typeOptions as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div class="ex-field ex-field--wide">
                    <label>근로자 <em>*</em></label>
                    <select id="ex-worker">
                        <option value="">— 선택 —</option>
                        @foreach ($workers as $w)<option value="{{ $w['value'] }}">{{ $w['label'] }}</option>@endforeach
                    </select>
                </div>
                <div class="ex-field">
                    <label id="ex-occurred-label">신청일 <em>*</em></label>
                    <input type="date" id="ex-occurred" value="{{ now(config('ndn.timezone'))->format('Y-m-d') }}">
                </div>
                <div class="ex-field" id="ex-noticed-wrap" hidden>
                    <label>인지일</label>
                    <input type="date" id="ex-noticed" value="{{ now(config('ndn.timezone'))->format('Y-m-d') }}">
                    <p class="ex-help">연락이 끊긴 날과 그것을 알게 된 날은 다릅니다.</p>
                </div>
                <div class="ex-field">
                    <label>사유</label>
                    <select id="ex-reason">
                        @foreach ($reasonOptions as $v => $l)<option value="{{ $v }}" @if($v === 'unknown') selected @endif>{{ $l }}</option>@endforeach
                    </select>
                    <p class="ex-help">이탈은 인지 시점에 모르는 게 정상입니다. <b>미상</b>으로 두고 나중에 확정하세요.</p>
                </div>
                <div class="ex-field ex-field--full">
                    <label>사유 상세</label>
                    <textarea id="ex-detail" rows="2" maxlength="2000" placeholder="구체적인 사정 (통계는 위 사유로 집계되고, 이 칸은 경위 기록입니다)"></textarea>
                </div>
                <div class="ex-field ex-field--full">
                    <label>메모</label>
                    <textarea id="ex-note" rows="2" maxlength="2000" placeholder="확인 경위 · 연락 시도 내역 등"></textarea>
                </div>
            </div>

            <p class="ex-warn" id="ex-warn" hidden>
                연락두절로 등록하면 <b>그 근로자의 앱 로그인이 곧바로 막힙니다.</b> 소재가 확인되면 되돌릴 수 있습니다.
            </p>

            <div class="ex-actions">
                <button type="button" id="ex-save" class="ex-btn">사건 등록</button>
            </div>
        </div>
    </div>

    {{-- 상세 --}}
    <div data-tabpane="detail" hidden>
        <div id="ex-detail-pane" class="ex-detailwrap"><div class="ex-empty">목록에서 건을 클릭하세요.</div></div>
    </div>

    {{-- 처리 창 --}}
    <div id="ex-modal" class="ex-modal" hidden>
        <div class="ex-box">
            <div class="ex-box__title" id="ex-box-title">처리</div>
            <p class="ex-box__msg" id="ex-box-msg"></p>

            <div class="ex-field" id="ex-m-reason-wrap">
                <label>사유 확정</label>
                <select id="ex-m-reason">
                    @foreach ($reasonOptions as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="ex-field">
                <label>사유 상세</label>
                <textarea id="ex-m-detail" rows="2" maxlength="2000"></textarea>
            </div>
            <div class="ex-field" id="ex-m-departed-wrap" hidden>
                <label>실제 출국일</label>
                <input type="date" id="ex-m-departed">
            </div>
            <div id="ex-m-report-wrap" hidden>
                <label class="ex-check"><input type="checkbox" id="ex-m-reported"> 출입국·경찰에 신고했습니다</label>
                <div class="ex-grid2">
                    <div class="ex-field"><label>신고일</label><input type="date" id="ex-m-reported-on"></div>
                    <div class="ex-field"><label>접수번호</label><input type="text" id="ex-m-report-ref" maxlength="100"></div>
                </div>
            </div>
            <div class="ex-field">
                <label>메모</label>
                <textarea id="ex-m-note" rows="2" maxlength="2000"></textarea>
            </div>

            <div class="ex-box__btns">
                <button type="button" class="ex-btn ex-btn--ghost" id="ex-m-close">닫기</button>
                <button type="button" class="ex-btn" id="ex-m-go">처리</button>
            </div>
        </div>
    </div>

    <style>
        .ex-hint{font-size:var(--mv2-fz-xs);color:var(--mv2-text-faint);margin:10px 2px 0;}
        .ex-dim{font-size:11px;color:var(--mv2-text-faint);}
        .ex-dim--b{display:block;}
        .ex-late{color:var(--mv2-pill-err-fg);font-weight:800;}
        .ex-type{font-size:11px;font-weight:700;border-radius:100px;padding:2px 9px;}
        .ex-type--early_return{background:#E8F0FE;color:#1a56c4;}
        .ex-type--absconded{background:var(--mv2-pill-err-bg);color:var(--mv2-pill-err-fg);}
        .ex-badge{font-size:11px;font-weight:700;border-radius:100px;padding:2px 9px;}
        .ex-badge--warn{background:#FEF3C7;color:#8a6d00;}
        .ex-badge--info{background:#E8F0FE;color:#1a56c4;}
        .ex-badge--bad{background:var(--mv2-pill-err-bg);color:var(--mv2-pill-err-fg);}
        .ex-badge--done{background:#E7F6EC;color:#1B7F43;}
        .ex-chip{font-size:11px;font-weight:700;background:var(--mv2-slate-25);color:var(--mv2-text-muted);border-radius:100px;padding:2px 9px;}
        .ex-form{background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:20px;max-width:860px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .ex-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 16px;}
        .ex-grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px 12px;margin-top:8px;}
        .ex-field{display:flex;flex-direction:column;gap:5px;}
        /* display:flex 가 hidden 속성의 기본 display:none 을 이긴다. 명시하지 않으면 숨겨지지 않는다. */
        .ex-field[hidden]{display:none;}
        .ex-field--wide{grid-column:span 2;}
        .ex-field--full{grid-column:1 / -1;}
        .ex-field>label{font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);}
        .ex-field>label em{color:var(--mv2-pill-err-fg);font-style:normal;}
        .ex-field input,.ex-field select,.ex-field textarea{font-family:inherit;font-size:var(--mv2-fz-sm);padding:8px 10px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);background:#fff;}
        .ex-field input:focus,.ex-field select:focus,.ex-field textarea:focus{outline:none;border-color:var(--mv2-primary-500);box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        .ex-help{font-size:11px;color:var(--mv2-text-faint);margin:0;}
        .ex-check{display:flex;align-items:center;gap:7px;font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-strong);cursor:pointer;}
        .ex-warn{font-size:var(--mv2-fz-xs);line-height:1.7;color:#8a6d00;background:#FEF3C7;border-radius:var(--mv2-r-sm);padding:10px 12px;margin:16px 0 0;}
        .ex-actions{display:flex;justify-content:flex-end;margin-top:18px;}
        .ex-btn{font-family:inherit;font-size:var(--mv2-fz-sm);font-weight:700;background:var(--mv2-primary-500);color:#fff;border:0;border-radius:var(--mv2-r-sm);padding:9px 20px;cursor:pointer;}
        .ex-btn:hover{background:var(--mv2-primary-600);}
        .ex-btn:disabled{background:var(--mv2-slate-25);color:var(--mv2-text-faint);cursor:not-allowed;}
        .ex-btn--ghost{background:#fff;color:var(--mv2-text-muted);border:1px solid var(--mv2-border-default);}
        .ex-btn--ghost:hover{background:var(--mv2-slate-25);}
        .ex-btn--sm{font-size:var(--mv2-fz-xs);padding:6px 14px;}
        .ex-tickets{border:1px dashed var(--mv2-border-default);border-radius:var(--mv2-r-sm);padding:12px 14px;margin-bottom:18px;background:var(--mv2-slate-25);}
        .ex-tickets__title{font-size:var(--mv2-fz-sm);font-weight:800;color:var(--mv2-text-strong);margin-bottom:4px;}
        .ex-tickets__help{font-size:11px;color:var(--mv2-text-faint);margin:0 0 8px;}
        .ex-ticket{display:block;width:100%;text-align:left;font-family:inherit;font-size:var(--mv2-fz-xs);background:#fff;border:1px solid var(--mv2-border-soft);border-radius:6px;padding:7px 10px;margin-bottom:5px;cursor:pointer;}
        .ex-ticket:hover{border-color:var(--mv2-primary-500);}
        .ex-detailwrap{background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:20px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 20px rgba(15,23,42,.05);}
        .ex-detail-head{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
        .ex-detail-head b{font-size:var(--mv2-fz-md);color:var(--mv2-text-strong);}
        .ex-dl{display:grid;grid-template-columns:130px 1fr;gap:0;margin:0;}
        .ex-dl dt{color:var(--mv2-text-muted);font-size:var(--mv2-fz-xs);font-weight:700;padding:8px 0;border-bottom:1px solid var(--mv2-border-soft);}
        .ex-dl dd{margin:0;font-size:var(--mv2-fz-sm);padding:8px 0;border-bottom:1px solid var(--mv2-border-soft);white-space:pre-wrap;}
        .ex-next{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px;}
        .ex-empty{color:var(--mv2-text-faint);font-size:var(--mv2-fz-sm);padding:10px 0;}
        .ex-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;z-index:900;padding:20px;}
        .ex-modal[hidden]{display:none;}
        .ex-box{background:#fff;border-radius:var(--mv2-r-lg);padding:22px;width:min(520px,96vw);max-height:90vh;overflow:auto;box-shadow:0 20px 50px rgba(15,23,42,.25);display:flex;flex-direction:column;gap:12px;}
        .ex-box__title{font-size:var(--mv2-fz-md);font-weight:800;color:var(--mv2-text-strong);}
        .ex-box__msg{font-size:var(--mv2-fz-xs);color:var(--mv2-text-muted);line-height:1.7;margin:0;}
        .ex-box__btns{display:flex;justify-content:flex-end;gap:8px;margin-top:6px;}
        @media (max-width:820px){.ex-grid{grid-template-columns:1fr;}.ex-field--wide{grid-column:auto;}}
    </style>
@endsection

@section('wwgrid')
<script>
    // 사건 기록은 **읽기 전용**이다. 조기 귀국·이탈은 무엇이 언제 있었나가 증빙이라
    // 나중에 표에서 고칠 수 있으면 안 된다. 처리(확정·취소 등)는 상세에서 한다.
    var exGrid = wwConsole({
        el: 'grid-exits',
        title: '조기귀국이탈',
        data: @json($rows, JSON_UNESCAPED_UNICODE),
        columns: [
            { header: '유형', name: 'type_label', width: 100, align: 'center', sortable: true },
            { header: '근로자', name: 'worker', width: 140, sortable: true },
            { header: '국적', name: 'nationality', width: 66, align: 'center' },
            { header: '농가', name: 'farm', width: 150, sortable: true },
            { header: '사유', name: 'reason_label', width: 110, align: 'center', sortable: true },
            { header: '기준일', name: 'occurred_cell', width: 165, align: 'center', sortable: true },
            { header: '경과', name: 'days_label', width: 74, align: 'center' },
            { header: '상태', name: 'status_label', width: 110, align: 'center', sortable: true },
            { header: '계정', name: 'worker_status', width: 90, align: 'center' },
            { header: '신고', name: 'reported_label', width: 74, align: 'center' },
            { header: '상세', name: 'detail', width: 74, align: 'center' },
        ],
    });

    // 편집기가 없는 칸이라 눌러도 셀이 열리지 않는다 → 상세를 여는 자리로 쓴다.
    document.getElementById('grid-exits').addEventListener('click', function (e) {
        var cell = e.target.closest('[data-col-name="detail"][data-row-index]');
        if (!cell) return;
        var row = exGrid.getData()[parseInt(cell.getAttribute('data-row-index'), 10)];
        if (row && row.id) window.exOpenDetail(row.id);
    });
</script>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/worker-exits') }}';
        var current = null;   // 열려 있는 건
        var target = null;    // 처리 창이 노리는 상태

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
            });
        }

        /* ---------- 등록 ---------- */
        var typeSel = document.getElementById('ex-type');

        // 유형에 따라 날짜 칸의 뜻이 바뀐다. 라벨을 안 바꾸면 엉뚱한 날짜가 들어온다.
        function syncType() {
            var absconded = typeSel.value === 'absconded';
            document.getElementById('ex-occurred-label').innerHTML =
                (absconded ? '마지막 연락일' : '신청일') + ' <em>*</em>';
            document.getElementById('ex-noticed-wrap').hidden = !absconded;
            document.getElementById('ex-warn').hidden = !absconded;
            document.getElementById('ex-reason').value = absconded ? 'unknown' : 'personal';
        }
        typeSel.addEventListener('change', syncType);
        syncType();

        // 앱에서 온 신청을 클릭하면 폼을 채운다
        [].forEach.call(document.querySelectorAll('.ex-ticket'), function (b) {
            b.addEventListener('click', function () {
                typeSel.value = 'early_return';
                syncType();
                document.getElementById('ex-worker').value = b.getAttribute('data-wid');
                b.dataset.picked = '1';
                [].forEach.call(document.querySelectorAll('.ex-ticket'), function (o) {
                    o.style.borderColor = o === b ? 'var(--mv2-primary-500)' : '';
                });
                ndnToast('신청을 폼에 붙였습니다. 사유를 확인하고 등록하세요.', { type: 'info' });
            });
        });

        function pickedTicket() {
            var b = document.querySelector('.ex-ticket[data-picked="1"]');
            return b ? b.getAttribute('data-tid') : null;
        }

        function post(url, body, done) {
            fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(body || {}),
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok || res.j.ok === false) {
                        var msg = res.j.message || (res.j.errors ? Object.values(res.j.errors)[0][0] : '처리하지 못했습니다.');
                        ndnToast(msg, { type: 'error' });
                        return;
                    }
                    done(res.j);
                })
                .catch(function () { ndnToast('처리하지 못했습니다.', { type: 'error' }); });
        }

        document.getElementById('ex-save').addEventListener('click', function () {
            var btn = this;
            var worker = document.getElementById('ex-worker').value;
            var occurred = document.getElementById('ex-occurred').value;
            if (!worker) { ndnToast('근로자를 선택하세요.', { type: 'error' }); return; }
            if (!occurred) { ndnToast('기준일을 입력하세요.', { type: 'error' }); return; }

            var body = {
                worker_id: Number(worker),
                type: typeSel.value,
                reason: document.getElementById('ex-reason').value,
                reason_detail: document.getElementById('ex-detail').value.trim() || null,
                occurred_on: occurred,
                note: document.getElementById('ex-note').value.trim() || null,
            };
            if (typeSel.value === 'absconded') {
                body.noticed_on = document.getElementById('ex-noticed').value || null;
            }
            var tid = pickedTicket();
            if (tid) body.support_ticket_id = Number(tid);

            btn.disabled = true; btn.textContent = '등록 중…';
            post(BASE, body, function () {
                ndnToast('사건을 등록했습니다.', { type: 'success' });
                setTimeout(function () { location.reload(); }, 900);
            });
            setTimeout(function () { btn.disabled = false; btn.textContent = '사건 등록'; }, 2000);
        });

        /* ---------- 상세 ---------- */
        function row(k, v) { return '<dt>' + esc(k) + '</dt><dd>' + esc(v == null || v === '' ? '—' : v) + '</dd>'; }

        function render(d) {
            var html = '<div class="ex-detail-head">'
                + '<b>' + esc(d.type_label) + ' #' + d.id + ' · ' + esc(d.worker) + '</b>'
                + '<span><span class="ex-badge ex-badge--' + d.tone + '">' + esc(d.status_label) + '</span> '
                + '<button type="button" class="ex-btn ex-btn--ghost ex-btn--sm" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></span>'
                + '</div>';

            html += '<dl class="ex-dl">'
                + row('근로자', d.worker + ' (' + d.nationality + ')')
                + row('계정 상태', d.worker_status)
                + row('농가', d.farm)
                + row(d.occurred_label, d.occurred_on)
                + (d.noticed_on ? row('인지일', d.noticed_on) : '')
                + (d.days !== null ? row('경과', d.days + '일') : '')
                + row('사유', d.reason_label)
                + row('사유 상세', d.reason_detail)
                + (d.departed_on ? row('실제 출국일', d.departed_on) : '')
                + (d.reported ? row('신고', '완료 · ' + (d.reported_on || '') + ' ' + (d.report_ref || '')) : '')
                + row('결정', d.decided_at ? d.decided_at + ' · ' + (d.decided_by || '') : '아직 결정 없음')
                + row('등록자', d.created_by)
                + row('메모', d.note)
                + '</dl>';

            if (d.ticket) {
                html += '<dl class="ex-dl">'
                    + row('연결된 민원', '#' + d.ticket.id + ' · ' + d.ticket.subject + ' (' + d.ticket.status + ')')
                    + row('민원 내용', d.ticket.body)
                    + '</dl>';
            }

            if (d.next && d.next.length) {
                html += '<div class="ex-next">';
                d.next.forEach(function (n) {
                    html += '<button type="button" class="ex-btn" data-go="' + n.value + '">' + esc(n.label) + '</button>';
                });
                html += '</div>';
            } else {
                html += '<p class="ex-empty">종결된 건입니다. 더 처리할 것이 없습니다.</p>';
            }

            document.getElementById('ex-detail-pane').innerHTML = html;
            document.getElementById('ex-detail-tab').hidden = false;
            window.ndnSwitchTab('detail');
        }

        function open(id) {
            fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) { current = d; render(d); })
                .catch(function () { ndnToast('불러오지 못했습니다.', { type: 'error' }); });
        }

        // 표는 위쪽 wwgrid 구역에서 만든다(그쪽이 먼저 실행된다). 표가 부를 수
        // 있도록 창구만 열어 둔다 — 표는 눌린 순간에야 이걸 찾으므로 순서 문제가 없다.
        window.exOpenDetail = function (id) { open(id); };

        /* ---------- 처리 창 ---------- */
        var modal = document.getElementById('ex-modal');

        // 상태마다 필요한 칸이 다르다. 다 보여 주면 무엇을 채워야 하는지 흐려진다.
        var MSG = {
            approved: '귀국을 승인합니다. 아직 출국 전이라 계정과 배정은 그대로 둡니다.',
            rejected: '신청을 반려합니다. 계속 근무하며, 연결된 민원이 함께 닫힙니다.',
            completed: '출국을 확인합니다. 계정이 <b>귀국</b>으로 바뀌고 <b>농가 배정이 취소</b>됩니다.',
            confirmed: '이탈로 확정합니다. 계정이 <b>이탈</b>로 바뀌고 <b>농가 배정이 취소</b>됩니다. 신고 여부를 함께 남기세요.',
            recovered: '소재가 확인됐습니다. 계정을 <b>재직</b>으로 되돌립니다.',
        };

        document.getElementById('ex-detail-pane').addEventListener('click', function (e) {
            var b = e.target.closest('[data-go]');
            if (!b || !current) return;

            target = b.getAttribute('data-go');
            document.getElementById('ex-box-title').textContent = current.type_label + ' — ' + b.textContent;
            document.getElementById('ex-box-msg').innerHTML = MSG[target] || '';
            document.getElementById('ex-m-reason').value = current.reason;
            document.getElementById('ex-m-detail').value = current.reason_detail || '';
            document.getElementById('ex-m-note').value = '';
            document.getElementById('ex-m-departed-wrap').hidden = target !== 'completed';
            document.getElementById('ex-m-report-wrap').hidden = target !== 'confirmed';
            document.getElementById('ex-m-go').textContent = b.textContent;
            modal.hidden = false;
        });

        document.getElementById('ex-m-close').addEventListener('click', function () { modal.hidden = true; });
        modal.addEventListener('click', function (e) { if (e.target === modal) modal.hidden = true; });

        document.getElementById('ex-m-go').addEventListener('click', function () {
            var btn = this;
            var body = {
                status: target,
                reason: document.getElementById('ex-m-reason').value,
                reason_detail: document.getElementById('ex-m-detail').value.trim() || null,
                note: document.getElementById('ex-m-note').value.trim() || null,
            };
            if (target === 'completed') {
                body.departed_on = document.getElementById('ex-m-departed').value || null;
            }
            if (target === 'confirmed') {
                body.reported = document.getElementById('ex-m-reported').checked;
                body.reported_on = document.getElementById('ex-m-reported-on').value || null;
                body.report_ref = document.getElementById('ex-m-report-ref').value.trim() || null;
            }

            btn.disabled = true;
            post(BASE + '/' + current.id + '/advance', body, function () {
                modal.hidden = true;
                ndnToast('처리했습니다.', { type: 'success' });
                setTimeout(function () { location.reload(); }, 900);
            });
            setTimeout(function () { btn.disabled = false; }, 2000);
        });
    })();
</script>
@endsection
