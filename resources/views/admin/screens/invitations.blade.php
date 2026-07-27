@extends('admin.screens.layout')
@section('title', '조직 초대')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">조직 초대</h1>
            <p class="screen__sub">시청·농가·송출기관·제휴 대리점을 초대합니다 · 초대 링크로만 가입 · 링크는 발송 시 1회 표시(복사)되며 서버에 평문 저장하지 않습니다</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">목록</button>
        <button type="button" class="screen-tab" data-tab="form">초대 발송</button>
    </div>

    <div data-tabpane="form" hidden>
    <div class="inv-send">
        <div class="inv-send__row">
            <div class="inv-field">
                <label>이메일</label>
                <input type="email" id="inv-email" placeholder="invitee@example.com">
            </div>
            <div class="inv-field">
                <label>역할</label>
                <select id="inv-role">
                    @foreach ($roleOptions as $o)
                        <option value="{{ $o['value'] }}">{{ $o['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="inv-field">
                <label>이름(선택)</label>
                <input type="text" id="inv-name" placeholder="담당자 이름">
            </div>
            <div class="inv-field inv-field--agency" style="display:none">
                <label>배정 대리점 ID(선택)</label>
                <input type="number" id="inv-agency" placeholder="대리점 ID">
            </div>
            <button type="button" id="inv-send" class="inv-sendbtn">초대 보내기</button>
        </div>
        <div id="inv-linkbox" class="inv-linkbox" style="display:none">
            <span class="inv-linkbox__label">초대 링크 (복사해서 전달하세요 · 1회 표시)</span>
            <div class="inv-linkbox__row">
                <input type="text" id="inv-link" readonly>
                <button type="button" id="inv-copy" class="inv-copybtn">복사</button>
            </div>
        </div>
    </div>
    </div>{{-- /탭:form --}}

    <div data-tabpane="list">
    <div class="signup-wrap">
        <table class="signup-table" id="inv-table">
            <thead>
                <tr>
                    <th style="width:60px">번호</th>
                    <th>이메일</th>
                    <th style="width:120px">역할</th>
                    <th style="width:90px">상태</th>
                    <th>초대자</th>
                    <th style="width:150px">발송</th>
                    <th style="width:150px">만료</th>
                    <th style="width:150px">처리</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr data-id="{{ $r['id'] }}">
                        <td class="c">{{ $r['id'] }}</td>
                        <td>{{ $r['email'] }}</td>
                        <td class="c">{{ $r['role'] }}</td>
                        <td class="c"><span class="inv-badge inv-badge--{{ $r['status'] }}">{{ $r['status_label'] }}</span></td>
                        <td>{{ $r['invited_by'] }}</td>
                        <td class="c">{{ $r['created'] }}</td>
                        <td class="c">{{ $r['expires'] }}</td>
                        <td class="c">
                            @if ($r['can_manage'])
                                <button type="button" class="su-btn su-btn--ok" data-act="resend">재발송</button>
                                <button type="button" class="su-btn su-btn--no" data-act="revoke">철회</button>
                            @else
                                <span class="inv-dash">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr id="inv-empty"><td colspan="8" class="su-empty">발송된 초대가 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </div>{{-- /탭:list --}}

    <style>
        .inv-send { border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-lg); background: #fff; padding: 16px; margin-bottom: 14px; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .inv-send__row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
        .inv-field { display: flex; flex-direction: column; gap: 4px; }
        .inv-field label { font-size: var(--mv2-fz-xs); font-weight: 700; color: var(--mv2-text-muted); }
        .inv-field input, .inv-field select { font-family: inherit; font-size: var(--mv2-fz-sm); padding: 8px 10px; border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-sm); min-width: 200px; }
        .inv-field input:focus, .inv-field select:focus { outline: none; border-color: var(--mv2-primary-500); box-shadow: 0 0 0 3px rgba(30,156,146,.15); }
        .inv-sendbtn { font-family: inherit; font-weight: 700; font-size: var(--mv2-fz-sm); background: var(--mv2-primary-500); color: #fff; border: 0; border-radius: var(--mv2-r-sm); padding: 9px 18px; cursor: pointer; }
        .inv-sendbtn:hover { background: var(--mv2-primary-600); }
        .inv-linkbox { margin-top: 14px; padding: 12px 14px; background: var(--mv2-primary-50, #E9F6F4); border-radius: var(--mv2-r-sm); }
        .inv-linkbox__label { font-size: var(--mv2-fz-xs); font-weight: 700; color: var(--mv2-primary-600); }
        .inv-linkbox__row { display: flex; gap: 8px; margin-top: 6px; }
        .inv-linkbox__row input { flex: 1; font-family: inherit; font-size: var(--mv2-fz-xs); padding: 8px 10px; border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-sm); background: #fff; }
        .inv-copybtn { font-family: inherit; font-weight: 700; font-size: var(--mv2-fz-xs); background: var(--mv2-primary-500); color: #fff; border: 0; border-radius: var(--mv2-r-sm); padding: 0 16px; cursor: pointer; }
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
        .inv-dash { color: var(--mv2-text-faint); }
        .inv-badge { font-size: 11px; font-weight: 700; border-radius: 100px; padding: 2px 9px; }
        .inv-badge--pending { background: var(--mv2-primary-50, #E9F6F4); color: var(--mv2-primary-600); }
        .inv-badge--accepted { background: #E7F6EC; color: #1B7F43; }
        .inv-badge--expired { background: var(--mv2-slate-25); color: var(--mv2-text-muted); }
        .inv-badge--revoked { background: var(--mv2-pill-err-bg); color: var(--mv2-pill-err-fg); }
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/invitations') }}';

        function jpost(url, body) {
            return fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(body || {}),
            }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); });
        }

        function showLink(url) {
            document.getElementById('inv-linkbox').style.display = '';
            document.getElementById('inv-link').value = url;
        }

        // 대리점 역할일 때만 배정 대리점 필드 표시
        var roleSel = document.getElementById('inv-role');
        function syncAgency() {
            document.querySelector('.inv-field--agency').style.display = (roleSel.value === 'partner_agency') ? '' : 'none';
        }
        roleSel.addEventListener('change', syncAgency); syncAgency();

        document.getElementById('inv-send').addEventListener('click', function () {
            var email = document.getElementById('inv-email').value.trim();
            if (!email) { ndnToast('이메일을 입력하세요.', { type: 'error' }); return; }
            var body = {
                email: email,
                role: roleSel.value,
                name: document.getElementById('inv-name').value.trim() || null,
                assigned_agency_id: document.getElementById('inv-agency').value || null,
            };
            jpost(BASE + '/send', body).then(function (res) {
                if (!res.ok) { ndnToast(res.j.message || '초대 실패', { type: 'error' }); return; }
                ndnToast('초대를 보냈습니다.', { type: 'success' });
                showLink(res.j.url);
                document.getElementById('inv-email').value = '';
                document.getElementById('inv-name').value = '';
                setTimeout(function () { location.reload(); }, 1200);
            });
        });

        document.getElementById('inv-copy').addEventListener('click', function () {
            var inp = document.getElementById('inv-link');
            inp.select();
            navigator.clipboard ? navigator.clipboard.writeText(inp.value).then(function () { ndnToast('링크를 복사했습니다.', { type: 'success' }); })
                                : (document.execCommand('copy'), ndnToast('링크를 복사했습니다.', { type: 'success' }));
        });

        document.getElementById('inv-table').addEventListener('click', function (e) {
            var btn = e.target.closest('.su-btn');
            if (!btn) return;
            var tr = btn.closest('tr[data-id]');
            var id = tr.getAttribute('data-id');
            var act = btn.getAttribute('data-act');

            if (act === 'resend') {
                jpost(BASE + '/' + id + '/resend').then(function (res) {
                    if (!res.ok) { ndnToast(res.j.message || '재발송 실패', { type: 'error' }); return; }
                    ndnToast('재발송했습니다. 새 링크를 복사하세요.', { type: 'success' });
                    showLink(res.j.url);
                    setTimeout(function () { location.reload(); }, 1400);
                });
            } else {
                ndnConfirm('이 초대를 철회할까요? 링크가 무효화됩니다.', { title: '초대 철회', okText: '철회', danger: true })
                    .then(function (ok) {
                        if (!ok) return;
                        jpost(BASE + '/' + id + '/revoke').then(function (res) {
                            if (!res.ok) { ndnToast(res.j.message || '철회 실패', { type: 'error' }); return; }
                            ndnToast('철회했습니다.', { type: 'success' });
                            setTimeout(function () { location.reload(); }, 800);
                        });
                    });
            }
        });
    })();
</script>
@endsection
