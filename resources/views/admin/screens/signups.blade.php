@extends('admin.screens.layout')
@section('title', '가입 승인')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">가입 승인</h1>
            <p class="screen__sub">근로자 셀프 가입 신청(승인 대기)을 검토·승인/거절합니다 · 승인해야 앱 로그인 가능 · 처리 내역은 감사 로그 기록(§7-6)</p>
        </div>
    </div>

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

    <style>
        .signup-wrap { border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-lg); overflow: hidden; background: #fff; box-shadow: 0 1px 2px rgba(15,23,42,.04), 0 6px 20px rgba(15,23,42,.05); }
        .signup-table { width: 100%; border-collapse: collapse; font-size: var(--mv2-fz-sm); }
        .signup-table thead th { text-align: left; background: var(--mv2-slate-25); color: var(--mv2-text-muted); font-weight: 700; font-size: var(--mv2-fz-xs); padding: 11px 14px; border-bottom: 1px solid var(--mv2-border-soft); white-space: nowrap; }
        .signup-table tbody td { padding: 11px 14px; border-bottom: 1px solid var(--mv2-border-soft); color: var(--mv2-text-strong); }
        .signup-table tbody tr:last-child td { border-bottom: 0; }
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

        function post(id, act, body) {
            return fetch(BASE + '/' + id + '/' + act, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(body || {}),
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
        }

        function removeRow(tr) {
            tr.parentNode.removeChild(tr);
            var tbody = document.querySelector('#signup-table tbody');
            if (!tbody.querySelector('tr[data-id]')) {
                tbody.innerHTML = '<tr id="su-empty"><td colspan="7" class="su-empty">승인 대기 중인 가입 신청이 없습니다.</td></tr>';
            }
        }

        document.getElementById('signup-table').addEventListener('click', function (e) {
            var btn = e.target.closest('.su-btn');
            if (!btn) return;
            var tr = btn.closest('tr[data-id]');
            var id = tr.getAttribute('data-id');
            var name = tr.children[1].textContent;
            var act = btn.getAttribute('data-act');

            if (act === 'approve') {
                ndnConfirm(name + ' 님의 가입을 승인할까요? 승인 시 앱 로그인이 가능해집니다.', { title: '가입 승인', okText: '승인' })
                    .then(function (ok) {
                        if (!ok) return;
                        post(id, 'approve').then(function (res) {
                            if (res.ok) { ndnToast('승인되었습니다.', { type: 'success' }); removeRow(tr); }
                            else { ndnToast(res.j.message || '승인 실패', { type: 'error' }); }
                        });
                    });
            } else {
                ndnConfirm(name + ' 님의 가입을 거절할까요? 거절 시 로그인할 수 없습니다.', { title: '가입 거절', okText: '거절', danger: true })
                    .then(function (ok) {
                        if (!ok) return;
                        post(id, 'reject').then(function (res) {
                            if (res.ok) { ndnToast('거절되었습니다.', { type: 'success' }); removeRow(tr); }
                            else { ndnToast(res.j.message || '거절 실패', { type: 'error' }); }
                        });
                    });
            }
        });
    })();
</script>
@endsection
