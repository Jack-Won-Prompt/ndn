@extends('admin.screens.layout')
@section('title', '가입 승인')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">가입 신청 심사</h1>
            <p class="screen__sub">웹·앱에서 들어온 신청을 검토합니다 · <strong>행 더블클릭</strong>으로 상세·제출 서류 확인 · <strong>합격하면 계정이 함께 열리고 합격 알림이 나갑니다</strong> · 처리 감사 로그(§7-6)</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">목록</button>
        <button type="button" class="screen-tab" data-tab="detail" id="su-detail-tab">상세</button>
    </div>

    <div data-tabpane="list">
        <div class="signup-wrap">
            <table class="signup-table" id="signup-table">
                <thead>
                    <tr>
                        <th style="width:60px">번호</th>
                        <th>이름</th>
                        <th>이메일</th>
                        <th style="width:70px">국적</th>
                        <th style="width:110px">지원 지역</th>
                        <th style="width:70px">언어</th>
                        <th style="width:70px">서류</th>
                        <th style="width:100px">진행</th>
                        <th style="width:140px">신청일시</th>
                        <th style="width:300px">처리</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr data-id="{{ $r['id'] }}">
                            <td class="c">{{ $r['id'] }}</td>
                            <td>{{ $r['name'] }}</td>
                            <td>{{ $r['email'] }}</td>
                            <td class="c">{{ $r['nationality'] }}</td>
                            <td class="c">{{ $r['city'] ?? '—' }}</td>
                            <td class="c">{{ $r['locale'] }}</td>
                            <td class="c {{ $r['files'] ? '' : 'su-nofile' }}">{{ $r['files'] ?: '없음' }}</td>
                            <td class="c"><span class="su-badge su-badge--{{ $r['tone'] }}">{{ $r['screening_label'] }}</span></td>
                            <td class="c">{{ $r['registered'] }}</td>
                            <td class="c">
                                <button type="button" class="su-btn su-btn--warn" data-act="supplement">보완 요청</button>
                                <button type="button" class="su-btn su-btn--ok" data-act="passed">합격</button>
                                <button type="button" class="su-btn" data-act="held">보류</button>
                                <button type="button" class="su-btn su-btn--no" data-act="failed">불합격</button>
                            </td>
                        </tr>
                    @empty
                        <tr id="su-empty"><td colspan="10" class="su-empty">심사할 가입 신청이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div data-tabpane="detail" hidden>
        <div id="su-detail" class="dtl"><div class="dtl-empty">목록에서 행을 더블클릭하면 상세·제출 서류와 처리 버튼이 표시됩니다.</div></div>
    </div>

    {{-- 보완 요청 창 --}}
    <div id="su-modal" class="su-modal" hidden>
        <div class="su-box">
            <div class="su-box__title">서류 보완 요청</div>
            <p class="su-box__msg">
                고른 항목이 <b>근로자 언어로</b> 메일에 담겨 나갑니다. 메일에는 이름·여권번호를 넣지 않고
                <b>부족한 항목 개수와 기한부 링크</b>만 보냅니다(§7-3). 링크는 {{ 14 }}일 뒤 만료됩니다.
            </p>

            <div class="su-box__label">무엇이 부족한가요? <em>*</em></div>
            <div class="su-items">
                @foreach ($supplementItems as $item)
                    <label class="su-item"><input type="checkbox" value="{{ $item }}"> {{ $item }}</label>
                @endforeach
            </div>

            <div class="su-box__label" style="margin-top:14px">담당자 메모 (선택)</div>
            <textarea id="su-note" rows="2" maxlength="500" placeholder="예: 여권 사진면이 흐려 글자가 안 보입니다."></textarea>
            <p class="su-help">이 메모는 <b>메일에 실리지 않습니다.</b> 콘솔에만 남습니다.</p>

            <div class="su-box__btns">
                <button type="button" class="su-btn" id="su-m-close">닫기</button>
                <button type="button" class="su-btn su-btn--warn" id="su-m-send">보완 요청 메일 보내기</button>
            </div>
        </div>
    </div>

    <style>
        .signup-wrap { border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-lg); overflow: hidden; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 6px 20px rgba(15,23,42,.05); }
        .signup-table { width: 100%; border-collapse: collapse; font-size: var(--mv2-fz-sm); }
        .signup-table thead th { text-align: left; background: var(--mv2-slate-25); color: var(--mv2-text-muted); font-weight: 700; font-size: var(--mv2-fz-xs); padding: 11px 14px; border-bottom: 1px solid var(--mv2-border-soft); white-space: nowrap; }
        .signup-table tbody td { padding: 11px 14px; border-bottom: 1px solid var(--mv2-border-soft); color: var(--mv2-text-strong); }
        .signup-table tbody tr:last-child td { border-bottom: 0; }
        .signup-table tbody tr[data-id] { cursor: pointer; }
        .signup-table tbody tr:hover { background: var(--mv2-slate-25); }
        .signup-table td.c { text-align: center; }
        .su-nofile { color: var(--mv2-text-faint); }
        .su-empty { text-align: center; color: var(--mv2-text-faint); padding: 34px 0; }
        .su-badge { font-size: 11px; font-weight: 700; border-radius: 100px; padding: 2px 9px; }
        .su-badge--info { background: #E8F0FE; color: #1a56c4; }
        .su-badge--warn { background: #FEF3C7; color: #8a6d00; }
        .su-badge--done { background: #E7F6EC; color: #1B7F43; }
        .su-badge--bad  { background: var(--mv2-pill-err-bg); color: var(--mv2-pill-err-fg); }
        /* 버튼 넷이 한 줄에 들어가야 한다. 줄바꿈되면 행 높이가 들쭉날쭉해진다. */
        .su-btn { font-family: inherit; font-size: var(--mv2-fz-xs); font-weight: 700; white-space: nowrap; background: #fff; color: var(--mv2-text-muted); border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-sm); padding: 5px 9px; cursor: pointer; margin: 0 1px; }
        .su-btn:hover { border-color: var(--mv2-text-strong); color: var(--mv2-text-strong); }
        .su-btn--ok { background: var(--mv2-primary-500); color: #fff; border-color: transparent; }
        .su-btn--ok:hover { background: var(--mv2-primary-600); color: #fff; }
        .su-btn--warn { background: #FEF3C7; color: #8a6d00; border-color: transparent; }
        .su-btn--warn:hover { background: #FDE68A; color: #8a6d00; }
        .su-btn--no { color: var(--mv2-pill-err-fg); }
        .su-btn--no:hover { background: var(--mv2-pill-err-bg); border-color: var(--mv2-pill-err-fg); color: var(--mv2-pill-err-fg); }
        .su-modal { position: fixed; inset: 0; background: rgba(15,23,42,.45); display: flex; align-items: center; justify-content: center; z-index: 900; padding: 20px; }
        .su-modal[hidden] { display: none; }
        .su-box { background: #fff; border-radius: var(--mv2-r-lg); padding: 22px; width: min(520px, 96vw); max-height: 90vh; overflow: auto; box-shadow: 0 20px 50px rgba(15,23,42,.25); }
        .su-box__title { font-size: var(--mv2-fz-md); font-weight: 800; color: var(--mv2-text-strong); margin-bottom: 10px; }
        .su-box__msg { font-size: var(--mv2-fz-xs); line-height: 1.7; color: #8a6d00; background: #FEF3C7; border-radius: var(--mv2-r-sm); padding: 10px 12px; margin: 0 0 14px; }
        .su-box__label { font-size: var(--mv2-fz-xs); font-weight: 700; color: var(--mv2-text-muted); margin-bottom: 7px; }
        .su-box__label em { color: var(--mv2-pill-err-fg); font-style: normal; }
        .su-items { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 12px; }
        .su-item { display: flex; align-items: center; gap: 7px; font-size: var(--mv2-fz-xs); color: var(--mv2-text-strong); cursor: pointer; }
        .su-box textarea { width: 100%; font-family: inherit; font-size: var(--mv2-fz-sm); padding: 8px 10px; border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-sm); resize: vertical; }
        .su-help { font-size: 11px; color: var(--mv2-text-faint); margin: 6px 0 0; }
        .su-box__btns { display: flex; justify-content: flex-end; gap: 8px; margin-top: 18px; }
        .su-box__btns .su-btn { padding: 8px 16px; font-size: var(--mv2-fz-sm); }
        @media (max-width: 820px) { .su-items { grid-template-columns: 1fr; } }
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/signups') }}';
        var LABELS = { address_kr: '국내 주소', emergency_contact: '비상 연락처' };
        var target = null;   // 보완 요청 창이 노리는 신청

        function esc(s) { return (s == null ? '' : String(s)); }

        function post(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(body || {}),
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j.ok !== false, j: j }; }); });
        }

        function removeRow(id) {
            var tr = document.querySelector('#signup-table tr[data-id="' + id + '"]');
            if (tr) tr.parentNode.removeChild(tr);
            var tbody = document.querySelector('#signup-table tbody');
            if (!tbody.querySelector('tr[data-id]')) {
                tbody.innerHTML = '<tr id="su-empty"><td colspan="10" class="su-empty">심사할 가입 신청이 없습니다.</td></tr>';
            }
        }

        /* ---------- 합격 / 보류 / 불합격 ---------- */
        // 무엇이 함께 일어나는지 확인창에 적는다. '합격'이 계정을 여는 줄 모르고 누르면 안 된다.
        var DECISION = {
            passed: {
                label: '합격',
                msg: ' 님을 합격 처리합니다. 계정이 곧바로 활성화되고 근로자에게 합격 알림이 갑니다.',
                danger: false,
            },
            held: {
                label: '보류',
                msg: ' 님의 신청을 보류합니다. 계정 상태는 그대로 승인 대기로 남습니다.',
                danger: false,
            },
            failed: {
                label: '불합격',
                msg: ' 님을 불합격 처리합니다. 로그인할 수 없게 되고 결과 알림이 갑니다.',
                danger: true,
            },
        };

        function decide(id, name, act) {
            var d = DECISION[act];
            if (!d) return;

            ndnConfirm(esc(name) + d.msg, { title: '가입 신청 ' + d.label, okText: d.label, danger: d.danger })
                .then(function (ok) {
                    if (!ok) return;
                    post(BASE + '/' + id + '/screen', { decision: act }).then(function (res) {
                        if (!res.ok) { ndnToast(res.j.message || '처리 실패', { type: 'error' }); return; }
                        ndnToast(d.label + ' 처리했습니다.', { type: 'success' });
                        // 보류는 목록에 남는다 — 아직 결정이 안 났다.
                        if (act !== 'held') {
                            removeRow(id);
                            document.getElementById('su-detail').innerHTML = '<div class="dtl-empty">처리되었습니다. 목록에서 다른 신청을 선택하세요.</div>';
                            window.ndnSwitchTab('list');
                        } else {
                            setTimeout(function () { location.reload(); }, 700);
                        }
                    });
                });
        }

        /* ---------- 보완 요청 ---------- */
        var modal = document.getElementById('su-modal');

        function openSupplement(id, name) {
            target = { id: id, name: name };
            document.getElementById('su-note').value = '';
            [].forEach.call(modal.querySelectorAll('.su-item input'), function (i) { i.checked = false; });
            modal.hidden = false;
        }

        document.getElementById('su-m-close').addEventListener('click', function () { modal.hidden = true; });
        modal.addEventListener('click', function (e) { if (e.target === modal) modal.hidden = true; });

        document.getElementById('su-m-send').addEventListener('click', function () {
            var btn = this;
            var items = [].map.call(modal.querySelectorAll('.su-item input:checked'), function (i) { return i.value; });

            if (!items.length) { ndnToast('부족한 항목을 하나 이상 고르세요.', { type: 'error' }); return; }

            btn.disabled = true;
            post(BASE + '/' + target.id + '/supplement', {
                items: items,
                note: document.getElementById('su-note').value.trim() || null,
            }).then(function (res) {
                btn.disabled = false;
                if (!res.ok) { ndnToast(res.j.message || '보내지 못했습니다.', { type: 'error' }); return; }
                modal.hidden = true;
                ndnToast('보완 요청 메일을 보냈습니다.', { type: 'success' });
                setTimeout(function () { location.reload(); }, 900);
            });
        });

        /* ---------- 목록 ---------- */
        document.getElementById('signup-table').addEventListener('click', function (e) {
            var btn = e.target.closest('.su-btn');
            if (!btn) return;
            var tr = btn.closest('tr[data-id]');
            var id = tr.getAttribute('data-id');
            var name = tr.children[1].textContent;

            if (btn.getAttribute('data-act') === 'supplement') { openSupplement(id, name); return; }
            decide(id, name, btn.getAttribute('data-act'));
        });

        document.getElementById('signup-table').addEventListener('dblclick', function (e) {
            if (e.target.closest('.su-btn')) return;
            var tr = e.target.closest('tr[data-id]');
            if (tr) openSignup(tr.getAttribute('data-id'));
        });

        /* ---------- 상세 ---------- */
        function openSignup(id) {
            fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (w) {
                    var html = '<div class="dtl-head"><b>' + esc(w.name) + ' · 가입 신청 '
                        + '<span class="su-badge su-badge--' + w.tone + '">' + esc(w.screening_label) + '</span></b>'
                        + '<div class="dtl-head__actions">'
                        + '<button type="button" class="su-btn su-btn--warn" data-act="supplement" data-id="' + w.id + '" data-name="' + esc(w.name) + '">보완 요청</button>'
                        + '<button type="button" class="su-btn su-btn--ok" data-act="passed" data-id="' + w.id + '" data-name="' + esc(w.name) + '">합격</button>'
                        + '<button type="button" class="su-btn" data-act="held" data-id="' + w.id + '" data-name="' + esc(w.name) + '">보류</button>'
                        + '<button type="button" class="su-btn su-btn--no" data-act="failed" data-id="' + w.id + '" data-name="' + esc(w.name) + '">불합격</button>'
                        + '<button type="button" class="dtl-back" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></div></div>';

                    html += '<dl class="dtl-dl">'
                        + '<dt>이름</dt><dd>' + esc(w.name) + '</dd>'
                        + '<dt>이메일(로그인 ID)</dt><dd>' + esc(w.email) + '</dd>'
                        + '<dt>국적</dt><dd>' + esc(w.nationality) + '</dd>'
                        + '<dt>여권번호</dt><dd>' + esc(w.passport_no || '—') + '</dd>'
                        + '<dt>생년월일</dt><dd>' + esc(w.birth_date || '—')
                        + (w.age != null ? ' (만 ' + w.age + '세)' : '') + '</dd>'
                        + '<dt>본국 전화</dt><dd>' + esc(w.phone_home_country || '—') + '</dd>'
                        + '<dt>지원 지역</dt><dd>' + esc(w.city || '—') + '</dd>'
                        + '<dt>언어</dt><dd>' + esc(w.locale) + '</dd>'
                        + '<dt>상태</dt><dd>승인 대기</dd>'
                        + '<dt>신청일시</dt><dd>' + esc(w.registered) + '</dd></dl>';

                    // 보완을 요청해 둔 건이면 무엇을 요청했는지 먼저 보여 준다.
                    if (w.supplement_items && w.supplement_items.length) {
                        html += '<div class="dtl-sec"><div class="dtl-sec__title">보완 요청한 항목 ('
                            + esc(w.supplement_requested_at || '') + ')</div><dl class="dtl-dl">'
                            + '<dt>요청 항목</dt><dd>' + w.supplement_items.map(esc).join(', ') + '</dd>'
                            + (w.screening_note ? '<dt>담당자 메모</dt><dd>' + esc(w.screening_note) + '</dd>' : '')
                            + '</dl></div>';
                    } else if (w.screening_note) {
                        html += '<div class="dtl-sec"><div class="dtl-sec__title">담당자 메모</div><dl class="dtl-dl">'
                            + '<dt>' + esc(w.screening_label) + '</dt><dd>' + esc(w.screening_note) + '</dd></dl></div>';
                    }

                    // 본인이 올린 서류
                    html += '<div class="dtl-sec"><div class="dtl-sec__title">제출 서류 (' + (w.files || []).length + '건)</div>';
                    if (w.files && w.files.length) {
                        html += '<dl class="dtl-dl">';
                        w.files.forEach(function (f) {
                            html += '<dt>' + esc(f.uploaded_at) + '</dt><dd>'
                                + (f.missing ? esc(f.name) + ' (파일 없음)' : '<a href="' + f.url + '">' + esc(f.name) + '</a>')
                                + ' · ' + esc(f.size) + '</dd>';
                        });
                        html += '</dl>';
                    } else {
                        html += '<div class="dtl-empty">올린 서류가 없습니다. 필요하면 [보완 요청]으로 받으세요.</div>';
                    }
                    html += '</div>';

                    // 온보딩(앱에서 낸 것)
                    html += '<div class="dtl-sec"><div class="dtl-sec__title">온보딩 제출</div>';
                    var o = w.onboarding;
                    if (o) {
                        html += '<dl class="dtl-dl"><dt>온보딩 상태</dt><dd>' + esc(o.status) + '</dd>';
                        var p = o.payload || {};
                        Object.keys(p).forEach(function (k) {
                            var v = p[k];
                            html += '<dt>' + esc(LABELS[k] || k) + '</dt><dd>' + esc((v && typeof v === 'object') ? JSON.stringify(v) : v) + '</dd>';
                        });
                        html += '</dl>';
                        if (o.has_signature && o.signature_url) {
                            html += '<div class="dtl-docs" style="margin-top:10px"><div class="dtl-doc"><a href="' + o.signature_url + '" target="_blank"><img src="' + o.signature_url + '" alt="전자서명" loading="lazy"></a><div class="dtl-doc__name">전자서명</div></div></div>';
                        }
                    } else {
                        html += '<div class="dtl-empty">아직 온보딩·전자서명 제출이 없습니다.</div>';
                    }
                    html += '</div>';

                    document.getElementById('su-detail').innerHTML = html;
                    document.getElementById('su-detail-tab').hidden = false;
                    window.ndnSwitchTab('detail');
                })
                .catch(function () { ndnToast('상세를 불러오지 못했습니다.', { type: 'error' }); });
        }

        document.getElementById('su-detail').addEventListener('click', function (e) {
            var b = e.target.closest('.su-btn'); if (!b) return;
            var id = b.getAttribute('data-id'), name = b.getAttribute('data-name');
            if (b.getAttribute('data-act') === 'supplement') { openSupplement(id, name); return; }
            decide(id, name, b.getAttribute('data-act'));
        });
    })();
</script>
@endsection
