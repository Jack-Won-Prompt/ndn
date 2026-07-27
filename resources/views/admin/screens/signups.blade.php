@extends('admin.screens.layout')
@section('title', '가입 승인')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">가입 승인</h1>
            <p class="screen__sub">셀프 가입 신청(승인 대기) 검토·승인/거절 · <strong>행 더블클릭</strong>으로 상세·제출 서류 확인 · 승인해야 앱 로그인 가능 · 처리 감사 로그(§7-6)</p>
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
                        <th style="width:80px">국적</th>
                        <th style="width:90px">언어</th>
                        <th style="width:150px">신청일시</th>
                        <th style="width:170px">처리</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr data-id="{{ $r['id'] }}">
                            <td class="c">{{ $r['id'] }}</td>
                            <td>{{ $r['name'] }}</td>
                            <td>{{ $r['email'] }}</td>
                            <td class="c">{{ $r['nationality'] }}</td>
                            <td class="c">{{ $r['locale'] }}</td>
                            <td class="c">{{ $r['registered'] }}</td>
                            <td class="c">
                                <button type="button" class="su-btn su-btn--ok" data-act="approve">승인</button>
                                <button type="button" class="su-btn su-btn--no" data-act="reject">거절</button>
                            </td>
                        </tr>
                    @empty
                        <tr id="su-empty"><td colspan="7" class="su-empty">승인 대기 중인 가입 신청이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div data-tabpane="detail" hidden>
        <div id="su-detail" class="dtl"><div class="dtl-empty">목록에서 행을 더블클릭하면 상세·제출 서류와 승인/거절 버튼이 표시됩니다.</div></div>
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
        .su-empty { text-align: center; color: var(--mv2-text-faint); padding: 34px 0; }
        .su-btn { font-family: inherit; font-size: var(--mv2-fz-xs); font-weight: 700; border: 1px solid transparent; border-radius: var(--mv2-r-sm); padding: 5px 12px; cursor: pointer; margin: 0 2px; }
        .su-btn--ok { background: var(--mv2-primary-500); color: #fff; }
        .su-btn--ok:hover { background: var(--mv2-primary-600); }
        .su-btn--no { background: #fff; color: var(--mv2-pill-err-fg); border-color: var(--mv2-border-default); }
        .su-btn--no:hover { background: var(--mv2-pill-err-bg); border-color: var(--mv2-pill-err-fg); }
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/signups') }}';
        var LABELS = { address_kr: '국내 주소', emergency_contact: '비상 연락처' };
        function esc(s) { return (s == null ? '' : String(s)); }

        function post(id, act) {
            return fetch(BASE + '/' + id + '/' + act, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: '{}',
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
        }

        function removeRow(id) {
            var tr = document.querySelector('#signup-table tr[data-id="' + id + '"]');
            if (tr) tr.parentNode.removeChild(tr);
            var tbody = document.querySelector('#signup-table tbody');
            if (!tbody.querySelector('tr[data-id]')) {
                tbody.innerHTML = '<tr id="su-empty"><td colspan="7" class="su-empty">승인 대기 중인 가입 신청이 없습니다.</td></tr>';
            }
        }

        function decide(id, name, act) {
            var isApprove = act === 'approve';
            ndnConfirm(name + ' 님의 가입을 ' + (isApprove ? '승인' : '거절') + '할까요? ' + (isApprove ? '승인 시 앱 로그인이 가능해집니다.' : '거절 시 로그인할 수 없습니다.'),
                { title: isApprove ? '가입 승인' : '가입 거절', okText: isApprove ? '승인' : '거절', danger: !isApprove })
                .then(function (ok) {
                    if (!ok) return;
                    post(id, act).then(function (res) {
                        if (res.ok) {
                            ndnToast(isApprove ? '승인되었습니다.' : '거절되었습니다.', { type: 'success' });
                            removeRow(id);
                            document.getElementById('su-detail').innerHTML = '<div class="dtl-empty">처리되었습니다. 목록에서 다른 신청을 선택하세요.</div>';
                            window.ndnSwitchTab('list');
                        } else { ndnToast(res.j.message || '처리 실패', { type: 'error' }); }
                    });
                });
        }

        // 목록의 승인/거절 버튼
        document.getElementById('signup-table').addEventListener('click', function (e) {
            var btn = e.target.closest('.su-btn');
            if (!btn) return;
            var tr = btn.closest('tr[data-id]');
            decide(tr.getAttribute('data-id'), tr.children[1].textContent, btn.getAttribute('data-act'));
        });

        // 행 더블클릭 → 상세 탭
        document.getElementById('signup-table').addEventListener('dblclick', function (e) {
            if (e.target.closest('.su-btn')) return;
            var tr = e.target.closest('tr[data-id]');
            if (tr) openSignup(tr.getAttribute('data-id'));
        });

        function openSignup(id) {
            fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (w) {
                    var html = '<div class="dtl-head"><b>' + esc(w.name) + ' · 가입 신청</b>'
                        + '<div class="dtl-head__actions">'
                        + '<button type="button" class="dtl-btn dtl-btn--ok" data-act="approve" data-id="' + w.id + '" data-name="' + esc(w.name) + '">승인</button>'
                        + '<button type="button" class="dtl-btn dtl-btn--no" data-act="reject" data-id="' + w.id + '" data-name="' + esc(w.name) + '">거절</button>'
                        + '<button type="button" class="dtl-back" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></div></div>';
                    html += '<dl class="dtl-dl">'
                        + '<dt>이름</dt><dd>' + esc(w.name) + '</dd>'
                        + '<dt>이메일(로그인 ID)</dt><dd>' + esc(w.email) + '</dd>'
                        + '<dt>국적</dt><dd>' + esc(w.nationality) + '</dd>'
                        + '<dt>언어</dt><dd>' + esc(w.locale) + '</dd>'
                        + '<dt>상태</dt><dd>승인 대기</dd>'
                        + '<dt>신청일시</dt><dd>' + esc(w.registered) + '</dd></dl>';

                    html += '<div class="dtl-sec"><div class="dtl-sec__title">제출 서류</div>';
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
                        html += '<div class="dtl-empty">아직 제출된 서류(온보딩·전자서명)가 없습니다.</div>';
                    }
                    html += '</div>';

                    document.getElementById('su-detail').innerHTML = html;
                    document.getElementById('su-detail-tab').hidden = false;
                    window.ndnSwitchTab('detail');
                })
                .catch(function () { ndnToast('상세를 불러오지 못했습니다.', { type: 'error' }); });
        }

        // 상세 탭의 승인/거절
        document.getElementById('su-detail').addEventListener('click', function (e) {
            var b = e.target.closest('.dtl-btn'); if (!b) return;
            decide(b.getAttribute('data-id'), b.getAttribute('data-name'), b.getAttribute('data-act'));
        });
    })();
</script>
@endsection
