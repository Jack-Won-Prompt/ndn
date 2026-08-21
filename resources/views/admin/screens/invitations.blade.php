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
        <div id="grid-invitations"></div>
        <p class="inv-hint">
            철회할 초대를 <strong>체크</strong>한 뒤 툴바의 <strong>[초대 철회]</strong>를 누르세요.
            재발송은 새 링크가 <strong>한 번만</strong> 보이므로 한 건씩 <strong>[재발송]</strong> 으로 합니다.
        </p>
    </div>{{-- /탭:list --}}

    <style>
        .inv-hint { font-size: var(--mv2-fz-xs); color: var(--mv2-text-faint); margin: 10px 2px 0; line-height: 1.7; }
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

@section('wwgrid')
<script>
    // 초대 기록은 **읽기 전용**이다. 발송한 내용을 나중에 고칠 수 있으면
    // '누구를 어떤 역할로 불렀나' 가 증빙이 되지 않는다. 처리(철회·재발송)만 한다.
    wwConsole({
        el: 'grid-invitations',
        title: '조직초대',
        data: @json($rows, JSON_UNESCAPED_UNICODE),
        rowCheckbox: true,
        buttons: [
            { label: '초대 철회', onClick: function (g) { window.invRevoke(g); } },
            { label: '재발송', onClick: function (g) { window.invResend(g); } },
        ],
        columns: [
            { header: '번호', name: 'id', width: 60, align: 'center', sortable: true },
            { header: '이메일', name: 'email', width: 240, sortable: true },
            { header: '역할', name: 'role', width: 120, align: 'center', sortable: true },
            { header: '상태', name: 'status_label', width: 90, align: 'center', sortable: true },
            { header: '초대자', name: 'invited_by', width: 130 },
            { header: '발송', name: 'created', width: 150, align: 'center', sortable: true },
            { header: '만료', name: 'expires', width: 150, align: 'center' },
        ],
    });
</script>
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

        /* ── 표 툴바에서 부르는 두 동작 ────────────────────────────────
         * 표 안에는 버튼을 둘 수 없어(편집기 없는 칸은 글자만 그린다) 체크 →
         * 툴바 순서로 처리한다. 표는 위쪽 wwgrid 구역에서 만들고, 그 표가 부를
         * 수 있도록 창구만 열어 둔다.
         */
        window.invRevoke = function (grid) {
            var rows = grid.getCheckedRows();
            if (!rows.length) { ndnToast('철회할 초대를 체크하세요.', { type: 'info' }); return; }

            var live = rows.filter(function (r) { return r.can_manage; }).length;
            var tail = rows.length - live
                ? ' (대기 중이 아닌 ' + (rows.length - live) + '건은 건너뜁니다)' : '';

            ndnConfirm(live + '건의 초대를 철회합니다' + tail + '. 링크가 무효화됩니다.',
                { title: '초대 철회', okText: '철회', danger: true })
                .then(function (ok) {
                    if (!ok) return;
                    jpost(BASE + '/revoke-bulk', { ids: rows.map(function (r) { return r.id; }) })
                        .then(function (res) {
                            if (!res.ok) { ndnToast(res.j.message || '철회 실패', { type: 'error' }); return; }
                            grid.setData(res.j.rows);
                            ndnToast(res.j.message, { type: 'success' });
                        });
                });
        };

        // 재발송은 새 링크가 **한 번만** 보인다. 여러 건을 한꺼번에 하면 링크를
        // 놓치므로 한 건씩만 받는다.
        window.invResend = function (grid) {
            var rows = grid.getCheckedRows().filter(function (r) { return r.can_manage; });

            if (rows.length !== 1) {
                ndnToast('재발송은 한 건씩만 됩니다. 대기 중인 초대 하나만 체크하세요.', { type: 'info' });
                return;
            }

            jpost(BASE + '/' + rows[0].id + '/resend').then(function (res) {
                if (!res.ok) { ndnToast(res.j.message || '재발송 실패', { type: 'error' }); return; }
                ndnToast('재발송했습니다. [초대 발송] 탭에서 새 링크를 복사하세요.', { type: 'success' });
                showLink(res.j.url);
                window.ndnSwitchTab('form');
                setTimeout(function () { location.reload(); }, 2500);
            });
        };
    })();
</script>
@endsection
