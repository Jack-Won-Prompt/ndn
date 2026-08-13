@extends('admin.screens.layout')
@section('title', '농가 매칭·배정')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">농가 매칭·배정</h1>
            <p class="screen__sub">수요를 고르면 조건에 맞는 인력이 추천됩니다 · 배정(제안) → 확정 순서로 진행하며, 확정하면 입국 준비 기록이 함께 만들어집니다.</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="demands">수요별 매칭<span class="screen-tab__badge">{{ count($rows) }}</span></button>
        <button type="button" class="screen-tab" data-tab="placements">배정 현황<span class="screen-tab__badge">{{ count($placements) }}</span></button>
    </div>

    {{-- 수요별 매칭 --}}
    <div data-tabpane="demands">
        <div class="mt-listwrap">
            <table class="mt-table" id="mt-demands">
                <thead>
                    <tr>
                        <th style="width:56px">번호</th>
                        <th>농가</th>
                        <th style="width:90px">지역</th>
                        <th style="width:90px">국적</th>
                        <th style="width:100px">품목</th>
                        <th style="width:110px">조건</th>
                        <th style="width:130px">진행</th>
                        <th style="width:170px">기간</th>
                        <th style="width:90px">상태</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr data-id="{{ $r['id'] }}">
                            <td class="c">{{ $r['id'] }}</td>
                            <td>{{ $r['farm'] }}@if ($r['allow_siblings']) <span class="mt-tag">형제동반</span>@endif</td>
                            <td class="c">{{ $r['city'] }}</td>
                            <td class="c">{{ $r['nationality'] }}</td>
                            <td class="c">{{ $r['crop'] ?: '—' }}</td>
                            <td class="c">{{ $r['gender'] }} · {{ $r['age'] }}</td>
                            <td class="c">
                                <b>{{ $r['filled'] }}</b> / {{ $r['headcount'] }}명
                                @if ($r['remaining'] > 0)<span class="mt-rem">{{ $r['remaining'] }} 남음</span>@else<span class="mt-full">충원 완료</span>@endif
                            </td>
                            <td class="c">{{ $r['period'] }}</td>
                            <td class="c"><span class="mt-badge">{{ $r['status_label'] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="mt-emptyrow">매칭을 진행할 수요가 없습니다. [수요 신청] 화면에서 먼저 등록·취합하세요.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="mt-panel" class="mt-panel" hidden></div>
        <p class="mt-hint">수요 행을 클릭하면 아래에 추천 인력과 이 농가의 배정 현황이 열립니다.</p>
    </div>

    {{-- 배정 현황 --}}
    <div data-tabpane="placements" hidden>
        <div class="mt-listwrap">
            <table class="mt-table" id="mt-placements">
                <thead>
                    <tr>
                        <th style="width:56px">번호</th>
                        <th style="width:130px">근로자</th>
                        <th style="width:90px">국적</th>
                        <th>농가</th>
                        <th style="width:170px">기간</th>
                        <th style="width:90px">상태</th>
                        <th style="width:150px">처리</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($placements as $p)
                        <tr data-pid="{{ $p['id'] }}">
                            <td class="c">{{ $p['id'] }}</td>
                            <td>{{ $p['worker'] }}@if ($p['group']) <span class="mt-tag">그룹</span>@endif</td>
                            <td class="c">{{ $p['nationality'] }}</td>
                            <td>{{ $p['farm'] }}@if ($p['note']) <span class="mt-note">{{ $p['note'] }}</span>@endif</td>
                            <td class="c">{{ $p['start_date'] }} ~ {{ $p['end_date'] }}</td>
                            <td class="c"><span class="mt-badge mt-badge--{{ $p['status'] }}">{{ $p['status_label'] }}</span></td>
                            <td class="c">
                                @if ($p['can_confirm'])<button type="button" class="mt-mini" data-confirm="{{ $p['id'] }}">확정</button>@endif
                                @if ($p['can_cancel'])<button type="button" class="mt-mini mt-mini--warn" data-cancel="{{ $p['id'] }}">취소</button>@endif
                                @if (! $p['can_confirm'] && ! $p['can_cancel'])—@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="mt-emptyrow">배정된 건이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .mt-listwrap{border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 20px rgba(15,23,42,.05);}
        .mt-table{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);}
        .mt-table thead th{text-align:left;background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-weight:700;font-size:var(--mv2-fz-xs);padding:10px 14px;border-bottom:1px solid var(--mv2-border-soft);white-space:nowrap;}
        .mt-table tbody td{padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);color:var(--mv2-text-strong);}
        .mt-table tbody tr:last-child td{border-bottom:0;}
        .mt-table tbody tr[data-id]{cursor:pointer;}
        .mt-table tbody tr[data-id]:hover{background:var(--mv2-slate-25);}
        .mt-table tbody tr.is-picked{background:var(--mv2-primary-50,#E9F6F4);}
        .mt-table td.c{text-align:center;}
        .mt-emptyrow{text-align:center;color:var(--mv2-text-faint);padding:34px 0;}
        .mt-hint{font-size:var(--mv2-fz-xs);color:var(--mv2-text-faint);margin:10px 2px 0;}
        .mt-tag{font-size:10px;font-weight:700;background:var(--mv2-slate-25);color:var(--mv2-text-muted);border-radius:100px;padding:1px 7px;margin-left:4px;}
        .mt-note{display:block;font-size:11px;color:var(--mv2-text-faint);}
        .mt-rem{display:block;font-size:11px;color:var(--mv2-primary-600);font-weight:700;}
        .mt-full{display:block;font-size:11px;color:var(--mv2-text-faint);}
        .mt-badge{font-size:11px;font-weight:700;border-radius:100px;padding:2px 9px;background:var(--mv2-slate-25);color:var(--mv2-text-muted);}
        .mt-badge--proposed{background:#FEF3C7;color:#8a6d00;}
        .mt-badge--confirmed{background:#E7F6EC;color:#1B7F43;}
        .mt-badge--cancelled{background:var(--mv2-pill-err-bg);color:var(--mv2-pill-err-fg);}
        .mt-mini{font-family:inherit;font-size:11px;font-weight:700;color:var(--mv2-primary-600);background:#fff;border:1px solid var(--mv2-border-default);border-radius:6px;padding:3px 10px;cursor:pointer;margin:0 2px;}
        .mt-mini:hover{background:var(--mv2-primary-50,#E9F6F4);}
        .mt-mini--warn{color:var(--mv2-pill-err-fg);}
        .mt-mini--warn:hover{background:var(--mv2-pill-err-bg);}
        .mt-panel{margin-top:14px;background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:18px;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 20px rgba(15,23,42,.05);}
        .mt-panel__head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:12px;flex-wrap:wrap;}
        .mt-panel__title{font-size:var(--mv2-fz-md);font-weight:800;color:var(--mv2-text-strong);}
        .mt-chips{display:flex;flex-wrap:wrap;gap:6px;}
        .mt-chip{font-size:11px;font-weight:700;background:var(--mv2-slate-25);color:var(--mv2-text-muted);border-radius:100px;padding:3px 10px;}
        .mt-sec{margin-top:16px;}
        .mt-sec__title{font-size:var(--mv2-fz-sm);font-weight:800;color:var(--mv2-text-strong);margin:0 0 8px;display:flex;align-items:center;gap:8px;}
        .mt-cands{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:8px;}
        .mt-cand{display:flex;align-items:flex-start;gap:8px;border:1px solid var(--mv2-border-soft);border-radius:8px;padding:9px 11px;cursor:pointer;background:#fff;}
        .mt-cand:hover{border-color:var(--mv2-primary-500);}
        .mt-cand.is-on{border-color:var(--mv2-primary-500);background:var(--mv2-primary-50,#E9F6F4);}
        .mt-cand input{margin-top:3px;}
        .mt-cand__name{font-weight:700;font-size:var(--mv2-fz-sm);color:var(--mv2-text-strong);}
        .mt-cand__meta{font-size:11px;color:var(--mv2-text-muted);margin-top:2px;}
        .mt-m{font-size:10px;border-radius:100px;padding:1px 6px;margin-right:3px;}
        .mt-m--ok{background:#E7F6EC;color:#1B7F43;}
        .mt-m--no{background:var(--mv2-pill-err-bg);color:var(--mv2-pill-err-fg);}
        .mt-m--unk{background:#FEF3C7;color:#8a6d00;}
        .mt-bar{display:flex;align-items:center;gap:12px;margin-top:14px;flex-wrap:wrap;}
        .mt-btn{font-family:inherit;font-size:var(--mv2-fz-sm);font-weight:700;background:var(--mv2-primary-500);color:#fff;border:0;border-radius:var(--mv2-r-sm);padding:9px 18px;cursor:pointer;}
        .mt-btn:hover{background:var(--mv2-primary-600);}
        .mt-btn:disabled{background:var(--mv2-slate-25);color:var(--mv2-text-faint);cursor:not-allowed;}
        .mt-chk{display:flex;align-items:center;gap:6px;font-size:var(--mv2-fz-xs);color:var(--mv2-text-muted);cursor:pointer;}
        .mt-empty{color:var(--mv2-text-faint);font-size:var(--mv2-fz-sm);padding:10px 0;}
        .mt-mini-table{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-xs);}
        .mt-mini-table td{padding:7px 8px;border-bottom:1px solid var(--mv2-border-soft);}
        .mt-mini-table tr:last-child td{border-bottom:0;}
        .mt-ask{position:fixed;inset:0;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;z-index:900;}
        .mt-ask__box{background:#fff;border-radius:var(--mv2-r-lg);padding:20px;width:min(420px,92vw);box-shadow:0 20px 50px rgba(15,23,42,.25);}
        .mt-ask__title{font-size:var(--mv2-fz-md);font-weight:800;color:var(--mv2-text-strong);margin-bottom:8px;}
        .mt-ask__msg{font-size:var(--mv2-fz-xs);color:var(--mv2-text-muted);margin:0 0 10px;line-height:1.6;}
        .mt-ask__input{width:100%;font-family:inherit;font-size:var(--mv2-fz-sm);padding:8px 10px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);resize:vertical;}
        .mt-ask__btns{display:flex;justify-content:flex-end;gap:8px;margin-top:12px;}
        .mt-ask__btns .mt-mini{padding:7px 16px;font-size:var(--mv2-fz-xs);}
        @media (max-width:820px){.mt-cands{grid-template-columns:1fr;}}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/matching') }}';
        var current = null;   // 열려 있는 수요
        var panel = document.getElementById('mt-panel');

        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
            });
        }

        // 항목별 대조 결과 — true/false 와 '정보 없음'(null)을 구분해 보여 준다.
        function matchTags(m) {
            var labels = { nationality: '국적', gender: '성별', age: '나이' };
            return Object.keys(labels).map(function (k) {
                if (!(k in m)) return '';
                var v = m[k];
                var cls = v === true ? 'ok' : (v === false ? 'no' : 'unk');
                var mark = v === true ? '○' : (v === false ? '✕' : '?');
                return '<span class="mt-m mt-m--' + cls + '">' + labels[k] + ' ' + mark + '</span>';
            }).join('');
        }

        function candCard(c) {
            var meta = [c.nationality, c.gender || '성별 미상', (c.age != null ? c.age + '세' : '나이 미상')].join(' · ');
            return '<label class="mt-cand" data-cand="' + c.id + '">'
                + '<input type="checkbox" value="' + c.id + '">'
                + '<span><span class="mt-cand__name">' + esc(c.name) + '</span>'
                + '<span class="mt-cand__meta">' + esc(meta) + '</span>'
                + (c.recommended ? '<span class="mt-cand__meta">' + matchTags(c.matches || {}) + '</span>' : '')
                + '</span></label>';
        }

        function placementRow(p) {
            var btns = '';
            if (p.can_confirm) btns += '<button type="button" class="mt-mini" data-confirm="' + p.id + '">확정</button>';
            if (p.can_cancel) btns += '<button type="button" class="mt-mini mt-mini--warn" data-cancel="' + p.id + '">취소</button>';
            return '<tr><td>' + esc(p.worker) + (p.group ? ' <span class="mt-tag">그룹</span>' : '') + '</td>'
                + '<td>' + esc(p.nationality) + '</td>'
                + '<td><span class="mt-badge mt-badge--' + p.status + '">' + esc(p.status_label) + '</span></td>'
                + '<td>' + esc(p.start_date) + ' ~ ' + esc(p.end_date) + '</td>'
                + '<td style="text-align:right">' + (btns || '—') + '</td></tr>';
        }

        function render(d) {
            var dm = d.demand;
            var cands = (d.candidates || []).concat(d.others || []);
            var html = '<div class="mt-panel__head">'
                + '<span class="mt-panel__title">' + esc(dm.farm) + ' · 수요 #' + dm.id + '</span>'
                + '<span class="mt-chips">'
                + '<span class="mt-chip">' + esc(dm.nationality) + '</span>'
                + '<span class="mt-chip">' + esc(dm.gender) + '</span>'
                + '<span class="mt-chip">' + esc(dm.age) + '</span>'
                + '<span class="mt-chip">' + esc(dm.crop || '품목 미정') + '</span>'
                + '<span class="mt-chip">' + esc(dm.period) + '</span>'
                + '<span class="mt-chip">' + dm.filled + ' / ' + dm.headcount + '명 (' + dm.remaining + ' 남음)</span>'
                + '</span></div>';

            html += '<div class="mt-sec"><div class="mt-sec__title">추천 인력 <span class="mt-chip">' + (d.candidates || []).length + '명</span>'
                + '<span class="mt-chip">기타 미배정 ' + (d.others || []).length + '명</span></div>';
            html += cands.length
                ? '<div class="mt-cands">' + cands.map(candCard).join('') + '</div>'
                : '<div class="mt-empty">배정할 수 있는 미배정·재직 인력이 없습니다. [근로자] 화면에서 등록하거나 [가입 승인]에서 승인하세요.</div>';

            html += '<div class="mt-bar">'
                + '<button type="button" class="mt-btn" id="mt-assign" disabled>선택 인원 배정</button>'
                + (dm.allow_siblings
                    ? '<label class="mt-chk"><input type="checkbox" id="mt-group"> 형제·가족으로 함께 배치 (한 그룹으로 묶음)</label>'
                    : '<span class="mt-chip">이 수요는 형제·가족 동반 불가</span>')
                + '<span class="mt-chip" id="mt-picked">0명 선택</span>'
                + '</div></div>';

            html += '<div class="mt-sec"><div class="mt-sec__title">이 농가의 배정 현황 <span class="mt-chip">' + (d.placements || []).length + '건</span></div>';
            html += (d.placements || []).length
                ? '<table class="mt-mini-table">' + d.placements.map(placementRow).join('') + '</table>'
                : '<div class="mt-empty">아직 배정된 인력이 없습니다.</div>';
            html += '</div>';

            panel.innerHTML = html;
            panel.hidden = false;
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function open(id) {
            current = id;
            [].forEach.call(document.querySelectorAll('#mt-demands tr[data-id]'), function (tr) {
                tr.classList.toggle('is-picked', tr.getAttribute('data-id') === String(id));
            });
            panel.hidden = false;
            panel.innerHTML = '<div class="mt-empty">불러오는 중…</div>';
            fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () { panel.innerHTML = '<div class="mt-empty">불러오지 못했습니다.</div>'; });
        }

        document.getElementById('mt-demands').addEventListener('click', function (e) {
            var tr = e.target.closest('tr[data-id]');
            if (tr) open(tr.getAttribute('data-id'));
        });

        // 후보 선택 → 버튼 활성화
        panel.addEventListener('change', function (e) {
            if (!e.target.matches('.mt-cand input')) return;
            e.target.closest('.mt-cand').classList.toggle('is-on', e.target.checked);
            var n = panel.querySelectorAll('.mt-cand input:checked').length;
            var btn = document.getElementById('mt-assign');
            if (btn) btn.disabled = n === 0;
            var badge = document.getElementById('mt-picked');
            if (badge) badge.textContent = n + '명 선택';
        });

        function post(url, body, done) {
            fetch(url, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(body || {}),
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok) {
                        var msg = res.j.message || (res.j.errors ? Object.values(res.j.errors)[0][0] : '처리하지 못했습니다.');
                        ndnToast(msg, { type: 'error' });
                        return;
                    }
                    done(res.j);
                })
                .catch(function () { ndnToast('처리하지 못했습니다.', { type: 'error' }); });
        }

        panel.addEventListener('click', function (e) {
            // 배정(제안) 생성
            if (e.target.id === 'mt-assign') {
                var ids = [].map.call(panel.querySelectorAll('.mt-cand input:checked'), function (i) { return Number(i.value); });
                if (!ids.length) return;
                var grp = document.getElementById('mt-group');
                e.target.disabled = true;
                post(BASE + '/placements', { demand_id: current, worker_ids: ids, as_group: !!(grp && grp.checked) }, function (j) {
                    ndnToast(j.count + '명 배정(제안)했습니다. 확정하면 입국 준비가 시작됩니다.', { type: 'success' });
                    open(current);
                });
                return;
            }
            var c = e.target.closest('[data-confirm]');
            if (c) { doConfirm(c.getAttribute('data-confirm'), function () { open(current); }); return; }
            var x = e.target.closest('[data-cancel]');
            if (x) { doCancel(x.getAttribute('data-cancel'), function () { open(current); }); }
        });

        function doConfirm(id, after) {
            ndnConfirm('배정을 확정합니다. 근로자에게 알림이 가고 입국 준비 기록이 만들어집니다.',
                { title: '배정 확정', okText: '확정' })
                .then(function (ok) {
                    if (!ok) return;
                    post(BASE + '/placements/' + id + '/confirm', {}, function () {
                        ndnToast('배정을 확정했습니다.', { type: 'success' });
                        after();
                    });
                });
        }

        // 취소 사유는 증빙으로 남는다(업무흐름 §4). 확인창만으로는 사유를 받을 수 없어
        // 입력칸이 있는 작은 창을 따로 띄운다.
        function askReason() {
            return new Promise(function (resolve) {
                var wrap = document.createElement('div');
                wrap.className = 'mt-ask';
                wrap.innerHTML = '<div class="mt-ask__box">'
                    + '<div class="mt-ask__title">배정 취소</div>'
                    + '<p class="mt-ask__msg">취소하면 이 근로자는 다시 미배정이 되어 다른 수요의 후보로 잡힙니다. 사유는 감사 기록에 함께 남습니다.</p>'
                    + '<textarea class="mt-ask__input" rows="3" placeholder="예: 농가 사정으로 수요 축소"></textarea>'
                    + '<div class="mt-ask__btns"><button type="button" class="mt-mini" data-no>닫기</button>'
                    + '<button type="button" class="mt-mini mt-mini--warn" data-yes>취소 처리</button></div></div>';
                document.body.appendChild(wrap);
                var ta = wrap.querySelector('textarea');
                ta.focus();
                wrap.addEventListener('click', function (e) {
                    if (e.target === wrap || e.target.hasAttribute('data-no')) {
                        wrap.parentNode.removeChild(wrap);
                        resolve(null);
                    } else if (e.target.hasAttribute('data-yes')) {
                        var v = ta.value.trim();
                        wrap.parentNode.removeChild(wrap);
                        resolve(v);
                    }
                });
            });
        }

        function doCancel(id, after) {
            askReason().then(function (reason) {
                if (reason === null) return;
                post(BASE + '/placements/' + id + '/cancel', { reason: reason }, function () {
                    ndnToast('배정을 취소했습니다.', { type: 'success' });
                    after();
                });
            });
        }

        // 배정 현황 탭
        document.getElementById('mt-placements').addEventListener('click', function (e) {
            var c = e.target.closest('[data-confirm]');
            if (c) { doConfirm(c.getAttribute('data-confirm'), function () { location.reload(); }); return; }
            var x = e.target.closest('[data-cancel]');
            if (x) { doCancel(x.getAttribute('data-cancel'), function () { location.reload(); }); }
        });
    })();
</script>
@endsection
