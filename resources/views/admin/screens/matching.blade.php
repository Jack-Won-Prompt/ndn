@extends('admin.screens.layout')
@section('title', '농가 매칭·배정')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">농가 매칭·배정</h1>
            <p class="screen__sub">
                농가를 등록한 자리에서 바로 인력을 붙입니다 · 농가 → 수요 → 인력 배정(제안) → 확정 순서로 진행하며,
                확정하면 입국 준비 기록이 함께 만들어집니다.
            </p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="farms">농가별 배정<span class="screen-tab__badge">{{ count($farmRows) }}</span></button>
        <button type="button" class="screen-tab" data-tab="demands">수요별 매칭<span class="screen-tab__badge">{{ count($rows) }}</span></button>
        <button type="button" class="screen-tab" data-tab="placements">배정 현황<span class="screen-tab__badge">{{ count($placements) }}</span></button>
    </div>

    {{-- 농가별 배정 — 농가를 여기서 등록하고 그 자리에서 사람을 붙인다 --}}
    <div data-tabpane="farms">
        <div id="grid-farms-mt"></div>
        <div id="mt-fpanel" class="mt-panel" hidden></div>
        <p class="mt-hint">
            농가는 이 표에서 바로 등록·수정합니다 (<strong>[신규 행] → 입력 → [변경 저장]</strong>, 엑셀 업로드도 같은 방식).
            <strong>[행 삭제]</strong>는 확인하면 그 자리에서 지웁니다.
            저장한 뒤 <strong>[인력 배정 ▸]</strong> 칸을 누르면 아래에 그 농가의 수요와 배정 화면이 열립니다.
            <br>표의 내용은 <strong>[농가·지자체 기준정보]</strong> 화면과 같은 자료입니다.
        </p>
    </div>

    {{-- 수요별 매칭 --}}
    <div data-tabpane="demands" hidden>
        <div id="grid-demands-mt"></div>
        <div id="mt-panel" class="mt-panel" hidden></div>
        <p class="mt-hint">
            <strong>[인력 배정 ▸]</strong> 칸을 누르면 아래에 추천 인력과 이 농가의 배정 현황이 열립니다.
            수요 자체를 고치려면 <strong>[수요 신청]</strong> 화면에서 하세요 — 여기서는 사람을 붙이는 일만 합니다.
        </p>
    </div>

    {{-- 배정 현황 --}}
    <div data-tabpane="placements" hidden>
        <div id="grid-placements-mt"></div>
        <p class="mt-hint">
            처리할 행을 <strong>체크</strong>한 뒤 툴바의 <strong>[배정 확정]</strong> · <strong>[배정 취소]</strong>를 누르세요.
            여러 건을 한 번에 처리할 수 있고, 처리할 수 없는 상태가 섞여 있으면 그 건만 건너뛰고 이유를 알려 줍니다.
        </p>
    </div>

    <style>
        .mt-hint{font-size:var(--mv2-fz-xs);color:var(--mv2-text-faint);margin:10px 2px 0;}
        .mt-tag{font-size:10px;font-weight:700;background:var(--mv2-slate-25);color:var(--mv2-text-muted);border-radius:100px;padding:1px 7px;margin-left:4px;}
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
        .mt-cand__name{display:block;font-weight:700;font-size:var(--mv2-fz-sm);color:var(--mv2-text-strong);}
        .mt-cand__meta{display:block;font-size:11px;color:var(--mv2-text-muted);margin-top:2px;}
        .mt-cand.is-off{display:none;}
        .mt-find{font-family:inherit;font-size:var(--mv2-fz-xs);padding:5px 10px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);width:180px;margin-left:auto;}
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
        .mt-demandpick{display:flex;flex-wrap:wrap;gap:8px;}
        .mt-dchip{font-family:inherit;text-align:left;background:#fff;border:1px solid var(--mv2-border-default);border-radius:8px;padding:8px 12px;cursor:pointer;font-size:var(--mv2-fz-xs);color:var(--mv2-text-strong);}
        .mt-dchip:hover{border-color:var(--mv2-primary-500);}
        .mt-dchip.is-on{border-color:var(--mv2-primary-500);background:var(--mv2-primary-50,#E9F6F4);}
        .mt-dchip b{display:block;font-size:var(--mv2-fz-sm);}
        .mt-dchip span{color:var(--mv2-text-muted);}
        .mt-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:8px;}
        /* display 를 지정한 요소는 hidden 속성만으로 사라지지 않는다. */
        .mt-form[hidden]{display:none;}
        .mt-form label{display:block;font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);margin-bottom:4px;}
        .mt-form input,.mt-form select{width:100%;font-family:inherit;font-size:var(--mv2-fz-sm);padding:7px 9px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);}
        .mt-form--full{grid-column:1/-1;display:flex;align-items:center;gap:12px;flex-wrap:wrap;}
        @media (max-width:820px){.mt-cands{grid-template-columns:1fr;}}
    </style>
@endsection

@section('wwgrid')
<script>
    // 농가 표 — 기준정보와 **같은 엔드포인트**로 저장·업로드한다.
    // 규칙이 두 벌로 갈라지면 어느 화면으로 넣었느냐에 따라 다른 데이터가 남는다.
    wwConsole({
        el: 'grid-farms-mt',
        editable: true,
        title: '농가',
        saveUrl: '{{ route('admin.grid.farms.save') }}',
        importUrl: '{{ route('admin.grid.farms.import') }}',
        // 저장 뒤 돌려받는 목록에 수요·배정 칸까지 담아 달라는 표시.
        // 이게 없으면 저장한 순간 [인력 배정] 칸이 빈칸이 된다.
        savePayload: { rows: 'matching' },
        // 농가는 기준정보다 — 지우면 매달린 화면들도 함께 정리된다.
        deleteWarning: '삭제하면 그 농가의 수요·배정·입국 기록·방문 점검·점검표도 함께 정리되고, 배정돼 있던 근로자는 미배정으로 풀립니다.',
        newRow: { name: '' },
        height: 340,
        data: @json($farmRows, JSON_UNESCAPED_UNICODE),
        columns: [
            { header: '농가명', name: 'name', width: 150, editor: 'text', sortable: true },
            { header: '지자체', name: 'city_id', width: 130, editor: 'combo', options: @json($cityOptions, JSON_UNESCAPED_UNICODE) },
            { header: '주작물', name: 'main_crop', width: 110, editor: 'text' },
            { header: '연락처', name: 'contact_phone', width: 140, editor: 'text' },
            { header: '주소', name: 'address', width: 220, editor: 'text' },
            { header: '경영체등록번호', name: 'business_reg_no', width: 140, editor: 'text' },
            // 아래 세 칸은 편집기가 없다 — 눌러도 셀이 열리지 않으므로 그대로 버튼처럼 쓴다.
            { header: '수요', name: 'demands', width: 70, align: 'center' },
            { header: '배정', name: 'placed', width: 70, align: 'center' },
            { header: '인력 배정', name: 'assign', width: 110, align: 'center' },
        ],
    });

    /* ── 수요별 매칭 · 배정 현황 ────────────────────────────────────────
     * 숨은 탭에서 표를 만들면 폭이 0 으로 잡혀 칸이 다 뭉개진다.
     * 탭이 처음 보이는 순간에 만든다(농가·지자체 기준정보와 같은 방식).
     */
    var mtGrids = {};

    function mtInitDemands() {
        if (mtGrids.demands) return;
        mtGrids.demands = wwConsole({
            el: 'grid-demands-mt',
            title: '수요별 매칭',
            data: @json($rows, JSON_UNESCAPED_UNICODE),
            height: 360,
            // 잘못 적은 신청서를 지울 수 있어야 한다. 배정은 농가에 매여 있어 함께 지우지 않는다.
            rowCheckbox: true,
            buttons: [
                { label: '수요 삭제', onClick: function (g) { window.mtDeleteDemands(g); } },
            ],
            columns: [
                { header: '번호', name: 'id', width: 60, align: 'center', sortable: true },
                { header: '농가', name: 'farm', width: 150, sortable: true },
                { header: '지역', name: 'city', width: 90, align: 'center' },
                { header: '국적', name: 'nationality', width: 66, align: 'center' },
                { header: '품목', name: 'crop', width: 90 },
                { header: '성별', name: 'gender', width: 66, align: 'center' },
                { header: '나이', name: 'age', width: 82, align: 'center' },
                // 정원 대비 얼마나 찼는지 — 이 화면에서 제일 자주 보는 숫자다.
                { header: '정원', name: 'headcount', width: 60, align: 'center' },
                { header: '배정', name: 'filled', width: 60, align: 'center' },
                { header: '남음', name: 'remaining', width: 60, align: 'center' },
                { header: '기간', name: 'period', width: 175, align: 'center' },
                { header: '상태', name: 'status_label', width: 110, align: 'center' },
                { header: '인력 배정', name: 'pick', width: 110, align: 'center' },
            ],
        });

        // 편집기가 없는 표라 어느 칸을 눌러도 셀이 열리지 않는다.
        document.getElementById('grid-demands-mt').addEventListener('click', function (e) {
            var cell = e.target.closest('[data-col-name="pick"][data-row-index]');
            if (!cell) return;
            var row = mtGrids.demands.getData()[parseInt(cell.getAttribute('data-row-index'), 10)];
            if (row && row.id) window.mtOpenDemand(row.id);
        });
    }

    function mtInitPlacements() {
        if (mtGrids.placements) return;
        mtGrids.placements = wwConsole({
            el: 'grid-placements-mt',
            title: '배정 현황',
            data: @json($placements, JSON_UNESCAPED_UNICODE),
            height: 460,
            // 셀 안에 버튼을 둘 수 없어(편집기 없는 칸은 글자만 그린다) 체크 → 툴바로 처리한다.
            rowCheckbox: true,
            buttons: [
                { label: '배정 확정', primary: true, onClick: function (g) { window.mtBulk(g, 'confirm'); } },
                { label: '배정 취소', onClick: function (g) { window.mtBulk(g, 'cancel'); } },
                { label: '배정 삭제', onClick: function (g) { window.mtBulk(g, 'delete'); } },
            ],
            columns: [
                { header: '번호', name: 'id', width: 60, align: 'center', sortable: true },
                { header: '근로자', name: 'worker', width: 160, sortable: true },
                { header: '국적', name: 'nationality', width: 66, align: 'center' },
                { header: '농가', name: 'farm', width: 150, sortable: true },
                { header: '동반', name: 'group_label', width: 60, align: 'center' },
                { header: '시작일', name: 'start_date', width: 105, align: 'center' },
                { header: '종료일', name: 'end_date', width: 105, align: 'center' },
                { header: '상태', name: 'status_label', width: 100, align: 'center' },
                { header: '비고', name: 'note', width: 220 },
            ],
        });
    }

    // 탭 전환(ui.js)이 패널을 보이게 한 다음(setTimeout 0) 표를 만든다.
    document.querySelector('.screen-tab[data-tab="demands"]')
        .addEventListener('click', function () { setTimeout(mtInitDemands, 0); });
    document.querySelector('.screen-tab[data-tab="placements"]')
        .addEventListener('click', function () { setTimeout(mtInitPlacements, 0); });
</script>
@endsection

@php
    // 수요를 그 자리에서 등록할 때 쓰는 국적 선택지.
    // 화살표 함수를 뷰 출력 안에 그대로 쓰면 Blade 가 인자 구분 쉼표로 오해할 수 있어 미리 만든다.
    $natOptions = collect(App\Domains\Recruitment\Enums\Nationality::adminOptions())
        ->map(fn (string $label, string $code) => ['value' => $code, 'label' => $label])
        ->values();
@endphp

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/matching') }}';
        var NATIONS = @json($natOptions, JSON_UNESCAPED_UNICODE);

        var panel = document.getElementById('mt-panel');    // 수요별 매칭 탭
        var fpanel = document.getElementById('mt-fpanel');  // 농가별 배정 탭

        // 지금 열려 있는 수요와, 그것을 그린 자리. 배정 뒤 같은 자리를 다시 그린다.
        var current = { demand: null, host: null };
        var currentFarm = null;

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

        /* ── 수요 1건: 추천 인력 + 이 농가의 배정 현황 ────────────────── */
        function demandHtml(d) {
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

            // 미배정 인력이 수십 명이라 목록이 길다. 이름으로 좁힐 칸을 둔다.
            html += '<div class="mt-sec"><div class="mt-sec__title">추천 인력 <span class="mt-chip">' + (d.candidates || []).length + '명</span>'
                + '<span class="mt-chip">기타 미배정 ' + (d.others || []).length + '명</span>'
                + '<input type="search" class="mt-find" placeholder="이름으로 찾기"></div>';
            html += cands.length
                ? '<div class="mt-cands">' + cands.map(candCard).join('') + '</div>'
                : '<div class="mt-empty">배정할 수 있는 미배정·재직 인력이 없습니다. [근로자] 화면에서 등록하거나 [가입 승인]에서 승인하세요.</div>';

            html += '<div class="mt-bar">'
                + '<button type="button" class="mt-btn" data-assign disabled>선택 인원 배정</button>'
                + (dm.allow_siblings
                    ? '<label class="mt-chk"><input type="checkbox" data-group> 형제·가족으로 함께 배치 (한 그룹으로 묶음)</label>'
                    : '<span class="mt-chip">이 수요는 형제·가족 동반 불가</span>')
                + '<span class="mt-chip" data-picked>0명 선택</span>'
                + '</div></div>';

            html += '<div class="mt-sec"><div class="mt-sec__title">이 농가의 배정 현황 <span class="mt-chip">' + (d.placements || []).length + '건</span></div>';
            html += (d.placements || []).length
                ? '<table class="mt-mini-table">' + d.placements.map(placementRow).join('') + '</table>'
                : '<div class="mt-empty">아직 배정된 인력이 없습니다.</div>';
            html += '</div>';

            return html;
        }

        function openDemand(id, host) {
            if (!host) return;
            current = { demand: id, host: host };
            host.hidden = false;
            host.innerHTML = '<div class="mt-empty">불러오는 중…</div>';
            fetch(BASE + '/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    host.innerHTML = demandHtml(d);
                    host.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                })
                .catch(function () { host.innerHTML = '<div class="mt-empty">불러오지 못했습니다.</div>'; });
        }

        /* ── 수요별 매칭 탭 ──────────────────────────────────────────────
         * 표는 위쪽 wwgrid 구역에서 만든다(그쪽이 먼저 실행된다). 그 표가 부를 수
         * 있도록 창구만 열어 둔다 — 표는 눌린 순간에야 이걸 찾으므로 순서 문제가 없다.
         */
        window.mtOpenDemand = function (id) { openDemand(id, panel); };

        /* ── 농가별 배정 탭 ──────────────────────────────────────────── */
        function demandChip(d) {
            return '<button type="button" class="mt-dchip" data-demand="' + d.id + '">'
                + '<b>#' + d.id + ' ' + esc(d.crop || '품목 미정') + ' · ' + d.headcount + '명</b>'
                + '<span>' + esc(d.period) + ' · ' + esc(d.status_label)
                + ' · 배정 ' + d.filled + '/' + d.headcount + '</span></button>';
        }

        // 수요가 없으면 배정 버튼이 닿을 곳이 없다 — 인원과 기간을 모르면 배정을
        // 만들 수 없기 때문이다. 그래서 그 자리에서 수요를 받는다.
        //
        // 이미 수요가 있으면 접어 둔다. 대부분은 있는 수요를 고르러 온 것이고,
        // 입력칸 여덟 개가 후보 명단을 아래로 밀어내면 정작 할 일이 멀어진다.
        function demandForm(collapsed) {
            var opts = NATIONS.map(function (n) {
                return '<option value="' + n.value + '">' + esc(n.label) + '</option>';
            }).join('');
            return (collapsed ? '<button type="button" class="mt-mini" data-newform>+ 이 농가에 수요 추가</button>' : '')
                + '<div class="mt-form"' + (collapsed ? ' hidden' : '') + '>'
                + '<div><label>국적</label><select data-f="nationality">' + opts + '</select></div>'
                + '<div><label>인원</label><input type="number" data-f="headcount" min="1" max="999" value="1"></div>'
                + '<div><label>성별</label><select data-f="gender">'
                + '<option value="any">무관</option><option value="male">남성</option><option value="female">여성</option>'
                + '</select></div>'
                + '<div><label>품목</label><input type="text" data-f="crop" maxlength="100" placeholder="예: 딸기"></div>'
                + '<div><label>시작일</label><input type="date" data-f="period_start"></div>'
                + '<div><label>종료일</label><input type="date" data-f="period_end"></div>'
                + '<div><label>최소 나이</label><input type="number" data-f="age_min" min="18" max="99" placeholder="무관"></div>'
                + '<div><label>최대 나이</label><input type="number" data-f="age_max" min="18" max="99" placeholder="무관"></div>'
                + '<div class="mt-form--full">'
                + '<label class="mt-chk"><input type="checkbox" data-f="allow_siblings"> 형제·가족 동반 허용</label>'
                + '<button type="button" class="mt-btn" data-newdemand>수요 등록</button>'
                + '</div></div>';
        }

        function farmHtml(d) {
            var f = d.farm;
            var ds = d.demands || [];
            var html = '<div class="mt-panel__head">'
                + '<span class="mt-panel__title">' + esc(f.name) + '</span>'
                + '<span class="mt-chips">'
                + '<span class="mt-chip">' + esc(f.city) + '</span>'
                + '<span class="mt-chip">' + esc(f.crop || '주작물 미정') + '</span>'
                + '<span class="mt-chip">배정 ' + (d.placements || []).length + '명</span>'
                + '</span></div>';

            html += '<div class="mt-sec"><div class="mt-sec__title">어느 수요에 채울까요 <span class="mt-chip">' + ds.length + '건</span></div>';
            html += ds.length
                ? '<div class="mt-demandpick">' + ds.map(demandChip).join('') + '</div>'
                : '<div class="mt-empty">이 농가에는 등록된 수요가 없습니다. 배정은 인원·기간이 정해진 수요에 대해서만 만들 수 있으므로, 아래에서 먼저 등록하세요.</div>';
            html += demandForm(ds.length > 0);
            html += '</div>';

            html += '<div id="mt-fbody"></div>';
            return html;
        }

        /**
         * 농가 패널을 그린다.
         *
         * pick 이 있으면 그 수요를 이어서 편다. 타이머로 나중에 부르지 않는 이유는,
         * 자료가 도착하기 전에 타이머가 먼저 울면 펼 자리(#mt-fbody)가 아직 없기 때문이다.
         */
        function openFarm(id, pick, keepScroll) {
            currentFarm = id;
            fpanel.hidden = false;
            if (!keepScroll) fpanel.innerHTML = '<div class="mt-empty">불러오는 중…</div>';
            return fetch(BASE + '/farms/' + id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    fpanel.innerHTML = farmHtml(d);
                    if (!keepScroll) fpanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    syncFarmRow(id, (d.placements || []).length, (d.demands || []).length);

                    var ds = d.demands || [];
                    // 수요가 하나뿐이면 한 번 더 고르게 할 이유가 없다.
                    var open = pick || (ds.length === 1 ? ds[0].id : null);
                    if (open) pickDemand(open);
                })
                .catch(function () { fpanel.innerHTML = '<div class="mt-empty">불러오지 못했습니다.</div>'; });
        }

        function pickDemand(id) {
            [].forEach.call(fpanel.querySelectorAll('.mt-dchip'), function (b) {
                b.classList.toggle('is-on', b.getAttribute('data-demand') === String(id));
            });
            openDemand(id, document.getElementById('mt-fbody'));
        }

        /**
         * 표의 '배정'·'수요' 숫자를 방금 본 값으로 맞춘다.
         *
         * 배정하고 나면 표는 옛 숫자를 들고 있다. 새로고침을 시키는 대신 그 줄만
         * 고쳐 쓴다 — 편집 중인 다른 줄을 날리지 않기 위해 setData 는 쓰지 않는다.
         */
        function syncFarmRow(farmId, placed, demands) {
            var host = document.getElementById('grid-farms-mt');
            var grid = host && host.wwgrid;
            if (!grid || !Array.isArray(grid.data) || typeof grid._refreshRow !== 'function') return;
            for (var i = 0; i < grid.data.length; i++) {
                if (String(grid.data[i].id) !== String(farmId)) continue;
                grid.data[i].placed = placed;
                grid.data[i].demands = demands;
                grid._refreshRow(i);
                return;
            }
        }

        // '인력 배정' 칸은 편집기가 없어 클릭이 셀 편집으로 먹히지 않는다.
        document.getElementById('grid-farms-mt').addEventListener('click', function (e) {
            var cell = e.target.closest('[data-col-name="assign"][data-row-index]');
            if (!cell) return;
            var host = document.getElementById('grid-farms-mt');
            var row = host.wwgrid && host.wwgrid.getData()[parseInt(cell.getAttribute('data-row-index'), 10)];
            if (!row) return;
            if (!row.id) {
                // 아직 저장하지 않은 신규 행에는 붙일 농가가 없다.
                ndnToast('먼저 [변경 저장]으로 농가를 등록하세요.', { type: 'info' });
                return;
            }
            openFarm(row.id);
        });

        /* ── 공통 동작 (두 패널이 같은 마크업을 쓴다) ────────────────── */
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

        // 배정 뒤 화면을 다시 그린다. 농가 탭에서 시작했으면 농가째로 다시 열어
        // 수요별 진행률과 표의 숫자까지 함께 맞춘다.
        function refresh() {
            // 배정 현황 표는 탭을 열 때 만들어진다. 패널에서 확정·취소하면 그 표가
            // 옛 숫자를 들고 있게 되므로, 다음에 열 때 새로 만들도록 버린다.
            if (mtGrids.placements) {
                mtGrids.placements = null;
                var host = document.getElementById('grid-placements-mt');
                if (host) { host.innerHTML = ''; if (host.previousElementSibling) host.previousElementSibling.remove(); }
            }

            if (current.host === document.getElementById('mt-fbody') && currentFarm) {
                openFarm(currentFarm, current.demand, true);
                return;
            }
            if (current.demand) openDemand(current.demand, current.host);
        }

        function onInput(e) {
            // 이름으로 좁히기 — 체크한 사람은 숨기지 않는다(안 보이는 채로 배정되면 안 된다).
            if (!e.target.matches('.mt-find')) return;
            var q = e.target.value.trim().toLowerCase();
            var scope = e.target.closest('.mt-sec');
            [].forEach.call(scope.querySelectorAll('.mt-cand'), function (c) {
                var hit = !q || c.textContent.toLowerCase().indexOf(q) !== -1;
                c.classList.toggle('is-off', !hit && !c.querySelector('input').checked);
            });
        }

        function onChange(e) {
            if (!e.target.matches('.mt-cand input')) return;
            e.target.closest('.mt-cand').classList.toggle('is-on', e.target.checked);
            var scope = e.target.closest('.mt-sec');
            var n = scope.querySelectorAll('.mt-cand input:checked').length;
            var btn = scope.querySelector('[data-assign]');
            if (btn) btn.disabled = n === 0;
            var badge = scope.querySelector('[data-picked]');
            if (badge) badge.textContent = n + '명 선택';
        }

        function onClick(e) {
            // 수요 고르기 (농가 탭)
            var chip = e.target.closest('[data-demand]');
            if (chip) { pickDemand(chip.getAttribute('data-demand')); return; }

            // 수요 입력칸 펴기 (농가 탭)
            if (e.target.hasAttribute('data-newform')) {
                var form = fpanel.querySelector('.mt-form');
                if (form) { form.hidden = false; e.target.hidden = true; }
                return;
            }

            // 수요 등록 (농가 탭)
            if (e.target.hasAttribute('data-newdemand')) { createDemand(e.target); return; }

            // 배정(제안) 생성
            if (e.target.hasAttribute('data-assign')) {
                var scope = e.target.closest('.mt-sec');
                var ids = [].map.call(scope.querySelectorAll('.mt-cand input:checked'), function (i) { return Number(i.value); });
                if (!ids.length) return;
                var grp = scope.querySelector('[data-group]');
                e.target.disabled = true;
                post(BASE + '/placements', { demand_id: current.demand, worker_ids: ids, as_group: !!(grp && grp.checked) }, function (j) {
                    ndnToast(j.count + '명 배정(제안)했습니다. 확정하면 입국 준비가 시작됩니다.', { type: 'success' });
                    refresh();
                });
                return;
            }
            var c = e.target.closest('[data-confirm]');
            if (c) { doConfirm(c.getAttribute('data-confirm'), refresh); return; }
            var x = e.target.closest('[data-cancel]');
            if (x) { doCancel(x.getAttribute('data-cancel'), refresh); }
        }

        [panel, fpanel].forEach(function (host) {
            host.addEventListener('input', onInput);
            host.addEventListener('change', onChange);
            host.addEventListener('click', onClick);
        });

        function createDemand(btn) {
            var wrap = btn.closest('.mt-form');
            var body = {};
            [].forEach.call(wrap.querySelectorAll('[data-f]'), function (el) {
                var k = el.getAttribute('data-f');
                body[k] = el.type === 'checkbox' ? el.checked : el.value;
            });
            // 비운 칸은 아예 보내지 않는다 — 빈 문자열을 보내면 '무관' 이 아니라 오류가 된다.
            ['age_min', 'age_max'].forEach(function (k) { if (body[k] === '') delete body[k]; });

            btn.disabled = true;
            post(BASE + '/farms/' + currentFarm + '/demand', body, function (j) {
                ndnToast('수요를 등록했습니다. 이어서 인력을 배정하세요.', { type: 'success' });
                // 방금 만든 수요를 바로 펴 준다 — 다시 찾아 누르게 하지 않는다.
                openFarm(currentFarm, j.demand_id, true);
            });
            // 실패했을 때만 다시 누를 수 있어야 한다. 성공하면 패널이 통째로 다시 그려진다.
            setTimeout(function () { if (btn.isConnected) btn.disabled = false; }, 1200);
        }

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
        function askReason(message, title) {
            return new Promise(function (resolve) {
                var wrap = document.createElement('div');
                wrap.className = 'mt-ask';
                wrap.innerHTML = '<div class="mt-ask__box">'
                    + '<div class="mt-ask__title">' + esc(title || '배정 취소') + '</div>'
                    + '<p class="mt-ask__msg">' + esc(message
                        || '취소하면 이 근로자는 다시 미배정이 되어 다른 수요의 후보로 잡힙니다.')
                    + ' 사유는 감사 기록에 함께 남습니다.</p>'
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

        /* ── 배정 현황 탭 — 체크한 건을 한 번에 ──────────────────────────
         * 표 안에는 버튼을 둘 수 없어(편집기 없는 칸은 글자만 그린다) 체크 → 툴바
         * 순서로 처리한다. 취소는 사유를 함께 받는다(업무흐름 §4).
         */
        window.mtBulk = function (grid, action) {
            // 삭제는 상태를 가리지 않는다 — 취소된 건도 목록에서 치울 수 있어야 한다.
            var rows = action === 'delete'
                ? grid.getCheckedRows()
                : grid.getCheckedRows().filter(function (r) {
                    return action === 'confirm' ? r.can_confirm : r.can_cancel;
                });
            var picked = grid.getCheckedRows().length;

            if (!picked) { ndnToast('처리할 행을 체크하세요.', { type: 'info' }); return; }
            if (!rows.length) {
                ndnToast('체크한 ' + picked + '건은 지금 ' + (action === 'confirm' ? '확정' : '취소')
                    + '할 수 있는 상태가 아닙니다.', { type: 'info' });
                return;
            }

            var ids = rows.map(function (r) { return r.id; });
            var skipped = picked - ids.length;

            function send(reason) {
                post(BASE + '/placements/bulk', { action: action, ids: ids, reason: reason }, function (j) {
                    grid.setData(j.rows);
                    // 확정·취소는 농가 정원을 움직인다. 수요 표의 진행률도 함께 맞춘다.
                    if (mtGrids.demands && Array.isArray(j.demand_rows)) mtGrids.demands.setData(j.demand_rows);
                    ndnToast(j.message, { type: 'success' });
                });
            }

            var tail = skipped ? ' (상태가 맞지 않는 ' + skipped + '건은 건너뜁니다)' : '';

            if (action === 'delete') {
                // 진행 중인 건이 섞였으면 사람이 농가에서 빠진다는 뜻이다. 그걸 먼저 말한다.
                var live = rows.filter(function (r) { return r.can_cancel; }).length;
                askReason(ids.length + '건을 목록에서 지웁니다.'
                    + (live ? ' 그중 진행 중인 ' + live + '건은 취소 처리되어 근로자가 미배정으로 돌아가고 농가 자리가 빕니다.' : ''),
                    '배정 삭제')
                    .then(function (reason) { if (reason !== null) send(reason); });
                return;
            }

            if (action === 'confirm') {
                ndnConfirm(ids.length + '건을 확정합니다' + tail
                    + '. 근로자에게 알림이 가고 입국 준비 기록이 만들어집니다.',
                    { title: '배정 확정', okText: '확정' })
                    .then(function (ok) { if (ok) send(null); });
                return;
            }

            askReason(ids.length + '건을 취소합니다' + tail
                + '. 취소하면 이 근로자들은 다시 미배정이 되어 다른 수요의 후보로 잡힙니다.')
                .then(function (reason) { if (reason !== null) send(reason); });
        };

        /* ── 수요 삭제 ─────────────────────────────────────────────────
         * 배정은 함께 지우지 않는다. 배정은 농가에 매여 있지 수요에 매여 있지 않아,
         * 잘못 적은 신청서를 지웠다고 이미 일하는 사람이 사라지면 안 된다.
         */
        window.mtDeleteDemands = function (grid) {
            var rows = grid.getCheckedRows();
            if (!rows.length) { ndnToast('지울 수요를 체크하세요.', { type: 'info' }); return; }

            var ids = rows.map(function (r) { return r.id; });
            var filled = rows.reduce(function (n, r) { return n + (r.filled || 0); }, 0);

            ndnConfirm(ids.length + '건의 수요를 지웁니다.'
                + (filled ? ' 이 농가들에 이미 배정된 ' + filled + '명은 그대로 남습니다 — 사람을 빼려면 [배정 현황] 에서 처리하세요.' : ''),
                { title: '수요 삭제', okText: '삭제', danger: true })
                .then(function (ok) {
                    if (!ok) return;
                    post(BASE + '/demands/delete', { ids: ids }, function (j) {
                        grid.setData(j.rows);
                        // 농가 표의 '수요' 숫자도 함께 어긋난다.
                        var fh = document.getElementById('grid-farms-mt');
                        if (fh && fh.wwgrid && Array.isArray(j.farm_rows)) fh.wwgrid.setData(j.farm_rows);
                        // 지운 수요를 펴 놓고 있었다면 그 패널도 닫는다.
                        if (current.demand && ids.indexOf(Number(current.demand)) !== -1) {
                            panel.hidden = true;
                            panel.innerHTML = '';
                            current = { demand: null, host: null };
                        }
                        ndnToast(j.message, { type: 'success' });
                    });
                });
        };
    })();
</script>
@endsection
