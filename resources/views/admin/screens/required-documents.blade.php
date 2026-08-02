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
        <div class="rd-wrap">
            <table class="rd-table" id="rd-table">
                <thead>
                    <tr>
                        <th style="width:56px">순서</th>
                        <th>문서</th>
                        <th style="width:70px">버전</th>
                        <th style="width:150px">번역 완료 언어</th>
                        <th style="width:80px">동의 필수</th>
                        <th style="width:90px">사용</th>
                        <th style="width:90px">동의 인원</th>
                    </tr>
                </thead>
                <tbody id="rd-tbody">
                    @foreach ($rows as $i => $r)
                        <tr data-id="{{ $r['id'] }}">
                            <td class="c">{{ $i + 1 }}</td>
                            <td><b>{{ $r['title'] ?: $r['code'] }}</b> <span class="rd-code">{{ $r['code'] }}</span></td>
                            <td class="c">v{{ $r['version'] }}</td>
                            <td class="c">
                                @foreach (['ko','bn','lo','si','vi'] as $loc)
                                    <span class="rd-loc rd-loc--{{ in_array($loc, $r['filled'], true) ? 'on' : 'off' }}">{{ $loc }}</span>
                                @endforeach
                            </td>
                            <td class="c">{{ $r['required'] ? '필수' : '열람만' }}</td>
                            <td class="c">
                                <span class="rd-badge rd-badge--{{ $r['active'] ? 'on' : 'off' }}">{{ $r['active'] ? '사용' : '미사용' }}</span>
                            </td>
                            <td class="c">{{ $r['agreed'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div data-tabpane="edit" hidden>
        <div id="rd-edit" class="dtl"><div class="dtl-empty">목록에서 문서를 클릭하면 본문 편집기가 열립니다.</div></div>
    </div>

    <style>
        .rd-wrap{border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .rd-table{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);}
        .rd-table thead th{text-align:left;background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-weight:700;font-size:var(--mv2-fz-xs);padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);white-space:nowrap;}
        .rd-table tbody td{padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);color:var(--mv2-text-strong);}
        .rd-table tbody tr:last-child td{border-bottom:0;}
        .rd-table tbody tr[data-id]{cursor:pointer;}
        .rd-table tbody tr[data-id]:hover{background:var(--mv2-slate-25);}
        .rd-table td.c{text-align:center;}
        .rd-code{color:var(--mv2-text-faint);font-size:var(--mv2-fz-xs);margin-left:6px;}
        .rd-loc{display:inline-block;min-width:22px;padding:1px 5px;margin:0 1px;border-radius:4px;font-size:11px;font-weight:700;}
        .rd-loc--on{background:#E7F3F1;color:#12695F;}
        .rd-loc--off{background:#F1F3F7;color:#9AA3B2;}
        .rd-badge{display:inline-block;padding:2px 9px;border-radius:100px;font-size:12px;font-weight:700;}
        .rd-badge--on{background:#E7F3F1;color:#12695F;}
        .rd-badge--off{background:#F1F3F7;color:#6B7280;}
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

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/required-documents') }}';
        var LOCALES = ['ko', 'bn', 'lo', 'si', 'vi'];
        var LOCALE_NAMES = { ko: '한국어', bn: '벵골어', lo: '라오어', si: '싱할라어', vi: '베트남어' };
        var doc = null;

        function esc(s) { return (s == null ? '' : String(s)); }

        function openDoc(id) {
            fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (d) {
                    doc = d;
                    var html = '<div class="dtl-head"><b>' + esc(d.code) + ' · v' + d.version + '</b>'
                        + '<div class="dtl-head__actions"><button type="button" class="dtl-back" onclick="window.ndnSwitchTab(\'list\')">← 목록</button></div></div>';

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

        document.getElementById('rd-table').addEventListener('click', function (e) {
            var tr = e.target.closest('tr[data-id]');
            if (tr) openDoc(tr.getAttribute('data-id'));
        });

        document.getElementById('rd-edit').addEventListener('click', function (e) {
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
