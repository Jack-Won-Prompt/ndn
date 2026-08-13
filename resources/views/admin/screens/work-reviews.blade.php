@extends('admin.screens.layout')
@section('title', '근무상태 점검표')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">근무상태 종합 점검표</h1>
            <p class="screen__sub">근로자 한 사람에 대한 현장 점검 · 근태·업무능력·생활·안전 43항목 · 응답으로 이탈 리스크 산정 (위치 추적 미사용)</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="list">점검 목록</button>
        <button type="button" class="screen-tab" data-tab="form">점검 작성</button>
        <button type="button" class="screen-tab" data-tab="shares">제출 이력<span class="screen-tab__badge">{{ count($shares) }}</span></button>
    </div>

    <div data-tabpane="list">
        <div id="grid-workreviews"></div>
    </div>

    {{-- 관계기관 제출 이력 --}}
    <div data-tabpane="shares" hidden>
        <div class="wrs-listwrap">
            <table class="wrs-table">
                <thead>
                    <tr>
                        <th style="width:150px">보낸 일시</th>
                        <th style="width:150px">받는 기관</th>
                        <th style="width:220px">이메일</th>
                        <th style="width:70px">건수</th>
                        <th>점검표</th>
                        <th style="width:100px">보낸 사람</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shares as $s)
                        <tr>
                            <td class="c">{{ $s['sent_at'] }}</td>
                            <td>{{ $s['org'] }}</td>
                            <td>{{ $s['email'] }}</td>
                            <td class="c">{{ $s['count'] }}건</td>
                            <td>{{ $s['reviews'] }}@if ($s['note'] !== '—')<span class="wrs-note">{{ $s['note'] }}</span>@endif</td>
                            <td class="c">{{ $s['sender'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="wrs-emptyrow">아직 관계기관에 제출한 이력이 없습니다. [점검 목록]에서 점검표를 체크하고 <b>관계기관 제출</b>을 누르세요.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- 제출 창 (점검 목록의 [관계기관 제출] 버튼이 연다) --}}
    <div id="wrs-modal" class="wrs-modal" hidden>
        <div class="wrs-box">
            <div class="wrs-box__title">관계기관 제출</div>
            <p class="wrs-box__warn">
                첨부되는 점검표 PDF 에는 <b>여권번호·생년월일 등 인적사항</b>이 들어갑니다(관공서 제출 서식).
                메일 본문과 첨부 파일명에는 인적사항을 넣지 않습니다. 보낸 기록은 이력에 남습니다.
            </p>
            <div class="wrs-picked" id="wrs-picked"></div>

            <div class="wrs-field">
                <label>받는 곳 <em>*</em> <span class="wrs-hint">기관명과 이메일. [+ 추가]로 최대 5곳</span></label>
                <div id="wrs-recips"></div>
                <button type="button" class="wrs-add" id="wrs-add">+ 받는 곳 추가</button>
                @if (count($recentRecipients))
                    <div class="wrs-recent">
                        최근 보낸 곳:
                        @foreach ($recentRecipients as $r)
                            <button type="button" class="wrs-chip" data-email="{{ $r['email'] }}" data-org="{{ $r['org'] }}">{{ $r['org'] ?: $r['email'] }}</button>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="wrs-field">
                <label>안내 문구 <span class="wrs-hint">메일 본문에 붙습니다. 이름·연락처는 넣을 수 없습니다</span></label>
                <textarea id="wrs-note" rows="3" maxlength="1000" placeholder="예: 요청하신 7월분 점검 결과를 제출합니다."></textarea>
            </div>

            <label class="wrs-ack"><input type="checkbox" id="wrs-ack"> 제출 근거(관계기관 요청·법령)를 확인했으며, 인적사항이 포함된 문서를 보냅니다.</label>

            <div class="wrs-box__btns">
                <button type="button" class="wrs-btn wrs-btn--ghost" id="wrs-close">닫기</button>
                <button type="button" class="wrs-btn" id="wrs-send">제출 메일 보내기</button>
            </div>
        </div>
    </div>

    <div data-tabpane="form" hidden>
        <div class="wr-form">
            <h2 class="wr-h2">점검 개요</h2>
            <div class="wr-grid">
                <div class="wr-field">
                    <label>근로자 <em>*</em></label>
                    <select id="wr-worker">
                        <option value="">선택하세요</option>
                        @foreach ($workers as $w)
                            <option value="{{ $w['value'] }}" data-farm="{{ $w['farm_id'] }}">{{ $w['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="wr-help" id="wr-farm-note">농가는 확정된 배정에서 자동으로 정해집니다.</p>
                </div>
                <div class="wr-field">
                    <label>점검일시 <em>*</em></label>
                    <input type="datetime-local" id="wr-at" value="{{ now(config('ndn.timezone'))->format('Y-m-d\TH:i') }}">
                </div>
                <div class="wr-field">
                    <label>점검장소</label>
                    <input type="text" id="wr-place" maxlength="200" placeholder="예: 농가 작업장 / 숙소">
                </div>
                <div class="wr-field">
                    <label>점검유형 <em>*</em></label>
                    <select id="wr-type">
                        @foreach ($typeOptions as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
            </div>

            @foreach ($sections as $s)
                <h2 class="wr-h2">{{ $s['label'] }}</h2>
                <table class="wr-items" data-section="{{ $s['key'] }}">
                    <thead>
                        <tr>
                            <th>점검항목</th>
                            @foreach ($s['options'] as $ov => $ol)<th style="width:96px">{{ $ol }}</th>@endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($s['items'] as $item)
                            <tr>
                                <td>{{ $item['label'] }}</td>
                                @foreach ($s['options'] as $ov => $ol)
                                    <td class="c">
                                        <input type="radio" name="wr-item-{{ $item['id'] }}" value="{{ $ov }}"
                                               data-item="{{ $item['id'] }}" @checked($loop->first)>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach

            <h2 class="wr-h2">연장근무 내역</h2>
            <div class="wr-grid">
                <div class="wr-field">
                    <label>실시 여부</label>
                    <select id="wr-ot-done"><option value="">—</option><option value="1">예</option><option value="0">아니오</option></select>
                </div>
                <div class="wr-field">
                    <label>연장근무 시간</label>
                    <input type="number" id="wr-ot-hours" min="0" max="999" step="0.5" placeholder="시간">
                </div>
                <div class="wr-field">
                    <label>근로자 동의 여부</label>
                    <select id="wr-ot-consent"><option value="">—</option><option value="1">예</option><option value="0">아니오</option></select>
                </div>
            </div>

            <h2 class="wr-h2">임금 및 계약사항</h2>
            <div class="wr-grid">
                <div class="wr-field">
                    <label>월 평균 임금</label>
                    <input type="text" id="wr-wage" maxlength="50" placeholder="예: 1,800,000원">
                    <p class="wr-help">개인 급여액이라 암호화해 보관합니다.</p>
                </div>
                <div class="wr-field">
                    <label>최근 임금 지급일</label>
                    <input type="date" id="wr-paid-on">
                </div>
                <div class="wr-field">
                    <label>임금 체불</label>
                    <select id="wr-unpaid"><option value="0">없음</option><option value="1">있음</option></select>
                    <p class="wr-help">있음으로 두면 다른 점수와 무관하게 고위험으로 잡힙니다.</p>
                </div>
                <div class="wr-field">
                    <label>숙식 제공</label>
                    <select id="wr-board"><option value="">—</option><option value="1">예</option><option value="0">아니오</option></select>
                </div>
                <div class="wr-field">
                    <label>근로계약 준수</label>
                    <select id="wr-contract"><option value="">—</option><option value="1">예</option><option value="0">아니오</option></select>
                </div>
                <div class="wr-field wr-field--full">
                    <label>계약 위반 사항</label>
                    <textarea id="wr-violation" rows="2"></textarea>
                </div>
            </div>

            <h2 class="wr-h2">종합 의견</h2>
            <div class="wr-grid">
                <div class="wr-field">
                    <label>점검 결과 <em>*</em></label>
                    <select id="wr-result">
                        @foreach ($resultOptions as $v => $l)<option value="{{ $v }}" @selected($v === 'good')>{{ $l }}</option>@endforeach
                    </select>
                    <p class="wr-help">[특별관리 대상]은 다른 점수와 무관하게 고위험으로 잡힙니다.</p>
                </div>
            </div>
            <div class="wr-grid">
                <div class="wr-field wr-field--full"><label>주요 특이사항</label><textarea id="wr-notable" rows="2"></textarea></div>
                <div class="wr-field wr-field--full"><label>개선 요구사항</label><textarea id="wr-improve" rows="2"></textarea></div>
                <div class="wr-field wr-field--full"><label>농가 건의사항</label><textarea id="wr-requests" rows="2"></textarea></div>
            </div>

            <h2 class="wr-h2">향후 조치사항</h2>
            <div class="wr-grid">
                <div class="wr-field"><label>개선기한</label><input type="date" id="wr-due"></div>
                <div class="wr-field"><label>담당자</label><input type="text" id="wr-assignee" maxlength="100"></div>
                <div class="wr-field"><label>재점검 예정일</label><input type="date" id="wr-recheck"></div>
                <div class="wr-field">
                    <label>보고 필요</label>
                    <div class="wr-checks">
                        <label><input type="checkbox" id="wr-report-city"> 지자체</label>
                        <label><input type="checkbox" id="wr-report-imm"> 출입국사무소</label>
                    </div>
                </div>
                <div class="wr-field wr-field--full"><label>기타 조치사항</label><textarea id="wr-action-note" rows="2"></textarea></div>
            </div>

            <h2 class="wr-h2">확인 및 서명</h2>
            <p class="wr-help" style="margin-bottom:12px">
                이 점검표는 관할 지자체·출입국이 요청하면 제출하는 자료입니다.
                이름만 적힌 표는 증빙이 되지 않으니 <b>서명까지 받아 두십시오.</b>
                통역인은 해당할 때만 받습니다.
            </p>
            <div class="wr-signs">
                @foreach ([
                    ['inspector', '점검자', $me],
                    ['farm', '농가 대표', ''],
                    ['worker', '외국인근로자', ''],
                    ['interpreter', '통역인 (해당 시)', ''],
                ] as [$role, $label, $prefill])
                    <div class="wr-sign" data-role="{{ $role }}">
                        <label>{{ $label }}</label>
                        <input type="text" id="wr-sign-{{ $role }}" maxlength="100"
                               placeholder="성명" value="{{ $prefill }}">
                        {{-- 손가락·펜으로 그린다. 현장에서 태블릿으로 쓰는 화면이다. --}}
                        <canvas class="wr-pad" data-pad="{{ $role }}" width="520" height="150"
                                aria-label="{{ $label }} 서명란"></canvas>
                        <div class="wr-sign__foot">
                            <span class="wr-sign__hint">위 칸에 서명하세요</span>
                            <button type="button" class="wr-sign__clear" data-clear="{{ $role }}">지우기</button>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="wr-actions">
                <button type="button" id="wr-save" class="wr-btn">점검표 저장</button>
            </div>
        </div>
    </div>

    <style>
        .wr-form{background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:22px;max-width:900px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .wr-h2{font-size:var(--mv2-fz-sm);font-weight:800;color:var(--mv2-text-strong);margin:26px 0 10px;padding-bottom:8px;border-bottom:1px solid var(--mv2-border-soft);}
        .wr-h2:first-child{margin-top:0;}
        .wr-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px 18px;}
        .wr-field{display:flex;flex-direction:column;gap:5px;}
        .wr-field--full{grid-column:1 / -1;}
        .wr-field label{font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);}
        .wr-field label em{color:var(--mv2-pill-err-fg);font-style:normal;}
        .wr-field select,.wr-field input,.wr-field textarea{font-family:inherit;font-size:var(--mv2-fz-sm);padding:8px 10px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);background:#fff;}
        .wr-field select:focus,.wr-field input:focus,.wr-field textarea:focus{outline:none;border-color:var(--mv2-primary-500);box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        .wr-help{font-size:12px;color:var(--mv2-text-faint);margin:0;}
        .wr-checks{display:flex;gap:14px;align-items:center;font-size:var(--mv2-fz-sm);padding-top:6px;}
        .wr-checks label{display:flex;align-items:center;gap:5px;font-weight:400;color:var(--mv2-text-strong);cursor:pointer;}
        .wr-items{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);border:1px solid var(--mv2-border-soft);border-radius:var(--mv2-r-sm);overflow:hidden;}
        .wr-items thead th{background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-size:var(--mv2-fz-xs);font-weight:700;text-align:left;padding:8px 12px;border-bottom:1px solid var(--mv2-border-soft);}
        .wr-items thead th+th{text-align:center;}
        .wr-items tbody td{padding:7px 12px;border-bottom:1px solid var(--mv2-border-soft);color:var(--mv2-text-strong);}
        .wr-items tbody tr:last-child td{border-bottom:0;}
        .wr-items td.c{text-align:center;}
        .wr-actions{display:flex;justify-content:flex-end;margin-top:22px;}
        .wr-btn{font-family:inherit;font-size:var(--mv2-fz-sm);font-weight:700;background:var(--mv2-primary-500);color:#fff;border:0;border-radius:var(--mv2-r-sm);padding:10px 22px;cursor:pointer;}
        .wr-btn:hover{background:var(--mv2-primary-600);}
        .wr-signs{display:grid;grid-template-columns:1fr 1fr;gap:16px 18px;}
        .wr-sign{display:flex;flex-direction:column;gap:6px;}
        .wr-sign label{font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);}
        .wr-sign input{font-family:inherit;font-size:var(--mv2-fz-sm);padding:8px 10px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);background:#fff;}
        .wr-sign input:focus{outline:none;border-color:var(--mv2-primary-500);box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        /* 서명란은 종이처럼 보이게. 손으로 그리므로 touch-action 을 꺼야 스크롤로 먹히지 않는다. */
        .wr-pad{width:100%;height:150px;border:1px dashed var(--mv2-border-default);border-radius:var(--mv2-r-sm);
            background:#fff;cursor:crosshair;touch-action:none;}
        .wr-pad.is-signed{border-style:solid;border-color:var(--mv2-primary-500);}
        .wr-sign__foot{display:flex;align-items:center;justify-content:space-between;}
        .wr-sign__hint{font-size:11px;color:var(--mv2-text-faint);}
        .wr-sign__clear{font-family:inherit;font-size:11px;font-weight:700;color:var(--mv2-text-muted);
            background:none;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);padding:3px 10px;cursor:pointer;}
        .wr-sign__clear:hover{border-color:var(--mv2-text-strong);color:var(--mv2-text-strong);}
        /* 관계기관 제출 */
        .wrs-listwrap{border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04),0 6px 20px rgba(15,23,42,.05);}
        .wrs-table{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);}
        .wrs-table thead th{text-align:left;background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-weight:700;font-size:var(--mv2-fz-xs);padding:10px 14px;border-bottom:1px solid var(--mv2-border-soft);white-space:nowrap;}
        .wrs-table tbody td{padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);color:var(--mv2-text-strong);}
        .wrs-table tbody tr:last-child td{border-bottom:0;}
        .wrs-table td.c{text-align:center;}
        .wrs-note{display:block;font-size:11px;color:var(--mv2-text-faint);margin-top:2px;}
        .wrs-emptyrow{text-align:center;color:var(--mv2-text-faint);padding:34px 0;}
        .wrs-modal{position:fixed;inset:0;background:rgba(15,23,42,.45);display:flex;align-items:center;justify-content:center;z-index:900;padding:20px;}
        .wrs-box{background:#fff;border-radius:var(--mv2-r-lg);padding:22px;width:min(560px,96vw);max-height:90vh;overflow:auto;box-shadow:0 20px 50px rgba(15,23,42,.25);}
        .wrs-box__title{font-size:var(--mv2-fz-md);font-weight:800;color:var(--mv2-text-strong);margin-bottom:10px;}
        .wrs-box__warn{font-size:var(--mv2-fz-xs);line-height:1.7;color:#8a6d00;background:#FEF3C7;border-radius:var(--mv2-r-sm);padding:10px 12px;margin:0 0 14px;}
        .wrs-picked{font-size:var(--mv2-fz-xs);color:var(--mv2-text-muted);background:var(--mv2-slate-25);border-radius:var(--mv2-r-sm);padding:9px 12px;margin-bottom:14px;}
        .wrs-field{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;}
        .wrs-field>label{font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-text-muted);}
        .wrs-field>label em{color:var(--mv2-pill-err-fg);font-style:normal;}
        .wrs-hint{font-weight:400;color:var(--mv2-text-faint);}
        .wrs-field textarea{font-family:inherit;font-size:var(--mv2-fz-sm);padding:8px 10px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);resize:vertical;}
        .wrs-recip{display:flex;gap:8px;margin-bottom:6px;}
        .wrs-recip input{font-family:inherit;font-size:var(--mv2-fz-sm);padding:8px 10px;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);}
        .wrs-recip input[data-org]{width:150px;flex:none;}
        .wrs-recip input[data-email]{flex:1;}
        .wrs-recip button{font-family:inherit;font-size:var(--mv2-fz-xs);color:var(--mv2-text-muted);background:none;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-sm);padding:0 10px;cursor:pointer;}
        .wrs-add{align-self:flex-start;font-family:inherit;font-size:var(--mv2-fz-xs);font-weight:700;color:var(--mv2-primary-600);background:none;border:1px dashed var(--mv2-border-default);border-radius:var(--mv2-r-sm);padding:5px 12px;cursor:pointer;}
        .wrs-recent{font-size:11px;color:var(--mv2-text-faint);margin-top:8px;display:flex;flex-wrap:wrap;gap:5px;align-items:center;}
        .wrs-chip{font-family:inherit;font-size:11px;font-weight:700;color:var(--mv2-text-muted);background:var(--mv2-slate-25);border:0;border-radius:100px;padding:3px 10px;cursor:pointer;}
        .wrs-chip:hover{background:var(--mv2-primary-50,#E9F6F4);color:var(--mv2-primary-600);}
        .wrs-ack{display:flex;align-items:flex-start;gap:7px;font-size:var(--mv2-fz-xs);color:var(--mv2-text-strong);line-height:1.6;cursor:pointer;}
        .wrs-box__btns{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;}
        .wrs-btn{font-family:inherit;font-size:var(--mv2-fz-sm);font-weight:700;background:var(--mv2-primary-500);color:#fff;border:0;border-radius:var(--mv2-r-sm);padding:9px 18px;cursor:pointer;}
        .wrs-btn:hover{background:var(--mv2-primary-600);}
        .wrs-btn:disabled{background:var(--mv2-slate-25);color:var(--mv2-text-faint);cursor:not-allowed;}
        .wrs-btn--ghost{background:#fff;color:var(--mv2-text-muted);border:1px solid var(--mv2-border-default);}
        .wrs-btn--ghost:hover{background:var(--mv2-slate-25);}
        @media (max-width:820px){.wr-grid{grid-template-columns:1fr;}.wr-signs{grid-template-columns:1fr;}.wrs-recip{flex-wrap:wrap;}.wrs-recip input[data-org]{width:100%;}}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var btn = document.getElementById('wr-save');

        function val(id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; }
        function tri(id) { var v = val(id); return v === '' ? null : v === '1'; }

        /* ---------- 서명란 ----------
           현장에서 태블릿으로 쓰는 화면이라 손가락·펜을 같이 받는다.
           캔버스 해상도를 화면 크기에 맞춰 잡지 않으면 그린 선이 어긋난다. */
        var pads = {};

        function setupPad(canvas) {
            var role = canvas.getAttribute('data-pad');
            var ctx = canvas.getContext('2d');
            var drawing = false;
            var dirty = false;

            function resize() {
                // 그리던 내용은 버린다. 다시 그리는 편이 늘어난 그림보다 낫다.
                var ratio = window.devicePixelRatio || 1;
                var w = canvas.clientWidth;
                var h = canvas.clientHeight;
                if (!w || !h) return;
                canvas.width = w * ratio;
                canvas.height = h * ratio;
                ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';
                ctx.strokeStyle = '#0F172A';
            }

            function pos(e) {
                var r = canvas.getBoundingClientRect();
                return { x: e.clientX - r.left, y: e.clientY - r.top };
            }

            canvas.addEventListener('pointerdown', function (e) {
                drawing = true;
                canvas.setPointerCapture(e.pointerId);
                var p = pos(e);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                e.preventDefault();
            });
            canvas.addEventListener('pointermove', function (e) {
                if (!drawing) return;
                var p = pos(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                if (!dirty) { dirty = true; canvas.classList.add('is-signed'); }
                e.preventDefault();
            });
            ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (ev) {
                canvas.addEventListener(ev, function () { drawing = false; });
            });

            resize();
            // 탭으로 열리는 화면이라 처음에 폭이 0 일 수 있다. 보일 때 한 번 더 잡는다.
            window.addEventListener('resize', resize);
            if (window.ResizeObserver) { new ResizeObserver(resize).observe(canvas); }

            pads[role] = {
                clear: function () {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    dirty = false;
                    canvas.classList.remove('is-signed');
                },
                // 그린 적 없으면 빈 PNG 를 보내지 않는다. 서버가 빈 서명을 저장하면
                // '서명 받음'으로 보여 증빙이 아닌 것이 증빙이 된다.
                data: function () { return dirty ? canvas.toDataURL('image/png') : null; },
            };
        }

        [].forEach.call(document.querySelectorAll('.wr-pad'), setupPad);

        document.addEventListener('click', function (e) {
            var b = e.target.closest('[data-clear]');
            if (b && pads[b.getAttribute('data-clear')]) { pads[b.getAttribute('data-clear')].clear(); }
        });

        document.getElementById('wr-worker').addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            var farm = opt ? opt.getAttribute('data-farm') : '';
            document.getElementById('wr-farm-note').textContent = farm
                ? '농가는 확정된 배정에서 자동으로 정해집니다.'
                : '이 근로자는 확정된 배정이 없어 점검표를 저장할 수 없습니다.';
        });

        btn.addEventListener('click', function () {
            if (!val('wr-worker')) { ndnToast('근로자를 선택하세요.', { type: 'error' }); return; }
            if (!val('wr-at')) { ndnToast('점검일시를 입력하세요.', { type: 'error' }); return; }

            var answers = {};
            [].forEach.call(document.querySelectorAll('.wr-items input[type="radio"]:checked'), function (r) {
                answers[r.getAttribute('data-item')] = r.value;
            });

            var payload = {
                worker_id: val('wr-worker'),
                reviewed_at: val('wr-at'),
                place: val('wr-place'),
                review_type: val('wr-type'),
                overtime_done: tri('wr-ot-done'),
                overtime_hours: val('wr-ot-hours') === '' ? null : val('wr-ot-hours'),
                overtime_consented: tri('wr-ot-consent'),
                avg_monthly_wage: val('wr-wage') || null,
                last_paid_on: val('wr-paid-on') || null,
                wage_unpaid: val('wr-unpaid') === '1',
                board_provided: tri('wr-board'),
                contract_followed: tri('wr-contract'),
                contract_violation: val('wr-violation') || null,
                result: val('wr-result'),
                notable: val('wr-notable') || null,
                improvements: val('wr-improve') || null,
                farm_requests: val('wr-requests') || null,
                action_due_on: val('wr-due') || null,
                action_assignee: val('wr-assignee') || null,
                recheck_on: val('wr-recheck') || null,
                report_city: document.getElementById('wr-report-city').checked,
                report_immigration: document.getElementById('wr-report-imm').checked,
                action_note: val('wr-action-note') || null,
                signed_inspector: val('wr-sign-inspector') || null,
                signed_farm: val('wr-sign-farm') || null,
                signed_worker: val('wr-sign-worker') || null,
                signed_interpreter: val('wr-sign-interpreter') || null,
                answers: answers,
                signatures: {
                    inspector: pads.inspector ? pads.inspector.data() : null,
                    farm: pads.farm ? pads.farm.data() : null,
                    worker: pads.worker ? pads.worker.data() : null,
                    interpreter: pads.interpreter ? pads.interpreter.data() : null,
                },
            };

            btn.disabled = true; btn.textContent = '저장 중…';
            fetch('{{ route('admin.work-reviews.store') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify(payload),
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (!res.ok || !res.j.ok) {
                        var m = res.j.message || (res.j.errors ? Object.values(res.j.errors)[0][0] : '저장하지 못했습니다.');
                        ndnToast(m, { type: 'error' });
                        btn.disabled = false; btn.textContent = '점검표 저장';
                        return;
                    }
                    ndnToast('점검표를 저장했습니다. 이탈 리스크: ' + res.j.risk + ' (' + res.j.score + '점)', { type: 'success' });
                    setTimeout(function () { location.reload(); }, 1200);
                })
                .catch(function () {
                    ndnToast('저장하지 못했습니다.', { type: 'error' });
                    btn.disabled = false; btn.textContent = '점검표 저장';
                });
        });

        /* ---------- 관계기관 제출 ----------
           점검 목록에서 체크한 점검표를 PDF 로 첨부해 이메일로 보낸다.
           그리드 툴바 버튼이 window.wrsOpen(ids) 를 부른다. */
        var modal = document.getElementById('wrs-modal');
        var recips = document.getElementById('wrs-recips');
        var picked = [];

        function recipRow(org, email) {
            var row = document.createElement('div');
            row.className = 'wrs-recip';
            row.innerHTML = '<input type="text" data-org placeholder="기관명 (예: 당진시청)" maxlength="100">'
                + '<input type="email" data-email placeholder="name@city.go.kr" maxlength="190">'
                + '<button type="button" data-del title="이 줄 지우기">✕</button>';
            if (org) row.querySelector('[data-org]').value = org;
            if (email) row.querySelector('[data-email]').value = email;
            recips.appendChild(row);
            return row;
        }

        window.wrsOpen = function (ids) {
            picked = ids;
            document.getElementById('wrs-picked').textContent =
                '선택한 점검표 ' + ids.length + '건 (#' + ids.join(', #') + ') — PDF ' + ids.length + '개가 첨부됩니다.';
            recips.innerHTML = '';
            recipRow();
            document.getElementById('wrs-note').value = '';
            document.getElementById('wrs-ack').checked = false;
            modal.hidden = false;
        };

        function close() { modal.hidden = true; }
        document.getElementById('wrs-close').addEventListener('click', close);
        modal.addEventListener('click', function (e) { if (e.target === modal) close(); });

        document.getElementById('wrs-add').addEventListener('click', function () {
            if (recips.children.length >= 5) { ndnToast('받는 곳은 5곳까지입니다.', { type: 'info' }); return; }
            recipRow();
        });

        recips.addEventListener('click', function (e) {
            if (!e.target.hasAttribute('data-del')) return;
            if (recips.children.length === 1) { ndnToast('받는 곳은 한 곳 이상 필요합니다.', { type: 'info' }); return; }
            e.target.closest('.wrs-recip').remove();
        });

        // 최근 보낸 곳 → 빈 줄에 채우거나 새 줄로 추가
        modal.addEventListener('click', function (e) {
            var chip = e.target.closest('.wrs-chip');
            if (!chip) return;
            var empty = [].find.call(recips.querySelectorAll('.wrs-recip'), function (r) {
                return !r.querySelector('[data-email]').value.trim();
            });
            var row = empty || (recips.children.length < 5 ? recipRow() : null);
            if (!row) { ndnToast('받는 곳은 5곳까지입니다.', { type: 'info' }); return; }
            row.querySelector('[data-org]').value = chip.getAttribute('data-org') || '';
            row.querySelector('[data-email]').value = chip.getAttribute('data-email');
        });

        document.getElementById('wrs-send').addEventListener('click', function () {
            var send = this;
            var list = [].map.call(recips.querySelectorAll('.wrs-recip'), function (r) {
                return { org: r.querySelector('[data-org]').value.trim(), email: r.querySelector('[data-email]').value.trim() };
            }).filter(function (r) { return r.email; });

            if (!list.length) { ndnToast('받는 이메일을 입력하세요.', { type: 'error' }); return; }
            if (!document.getElementById('wrs-ack').checked) {
                ndnToast('제출 근거 확인란에 체크해 주세요.', { type: 'error' }); return;
            }

            // PDF 를 그 자리에서 만들어 보내므로 시간이 걸린다. 두 번 눌리지 않게 잠근다.
            send.disabled = true; send.textContent = '보내는 중…';
            fetch('{{ route('admin.work-reviews.share') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({
                    review_ids: picked,
                    recipients: list,
                    note: document.getElementById('wrs-note').value.trim(),
                    acknowledged: 1,
                }),
            })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    send.disabled = false; send.textContent = '제출 메일 보내기';
                    if (!res.ok) {
                        var msg = res.j.message || (res.j.errors ? Object.values(res.j.errors)[0][0] : '보내지 못했습니다.');
                        ndnToast(msg, { type: 'error' });
                        return;
                    }
                    close();
                    ndnToast(res.j.reviews + '건을 ' + res.j.recipients + '곳에 제출했습니다.', { type: 'success' });
                    setTimeout(function () { location.reload(); }, 1200);
                })
                .catch(function () {
                    send.disabled = false; send.textContent = '제출 메일 보내기';
                    ndnToast('보내지 못했습니다.', { type: 'error' });
                });
        });
    })();
</script>
@endsection

@section('wwgrid')
<script>
    wwConsole({
        el: 'grid-workreviews',
        editable: false,
        title: '근무상태점검표',
        data: @json($rows),
        // 읽기전용 목록이지만 골라서 관계기관에 보내야 해 체크박스를 켠다.
        rowCheckbox: true,
        buttons: [{
            label: '관계기관 제출',
            primary: true,
            onClick: function (grid) {
                var ids = grid.getCheckedRows().map(function (r) { return r.id; });
                if (!ids.length) { ndnToast('제출할 점검표를 체크하세요.', { type: 'info' }); return; }
                if (ids.length > 10) { ndnToast('한 번에 10건까지 보낼 수 있습니다.', { type: 'error' }); return; }
                window.wrsOpen(ids);
            },
        }],
        columns: [
            { header: '번호', name: 'id', width: 60, align: 'center', sortable: true },
            { header: '근로자', name: 'worker', width: 120, sortable: true },
            { header: '시·군', name: 'city', width: 100, sortable: true },
            { header: '농가', name: 'farm', width: 140, sortable: true },
            { header: '점검일시', name: 'date', width: 140, align: 'center', sortable: true },
            { header: '유형', name: 'type', width: 90, align: 'center', sortable: true },
            { header: '점검 결과', name: 'result', width: 110, align: 'center', sortable: true },
            { header: '이탈 리스크', name: 'risk', width: 100, align: 'center', sortable: true },
            { header: '점수', name: 'score', width: 70, align: 'center', sortable: true },
            { header: '점검자', name: 'inspector', width: 100 },
            { header: '서명', name: 'signs', width: 70, align: 'center' },
            { header: '재점검', name: 'recheck', width: 100, align: 'center' },
            { header: '보고', name: 'report', width: 110, align: 'center' },
        ],
        onRowDblClick: function (row) {
            fetch('{{ url('admin/work-reviews') }}/' + row.id, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (d) {
                    var rows = [
                        ['근로자', d.worker + ' (' + d.nationality + ')'],
                        ['소속', (d.city || '—') + ' · ' + (d.farm || '—')],
                        ['점검일시', d.reviewed_at], ['점검장소', d.place || '—'],
                        ['점검유형', d.type], ['점검자', d.inspector || '—'],
                        ['점검 결과', d.result], ['이탈 리스크', d.risk + ' (' + d.score + '점)'],
                    ];
                    d.sections.forEach(function (s) {
                        var lines = s.answers.map(function (a) {
                            return (a.bad ? '⚠ ' : '· ') + a.label + ' — ' + a.value + (a.note ? ' (' + a.note + ')' : '');
                        });
                        rows.push([s.label, lines.length ? lines.join('\n') : '응답 없음']);
                    });
                    rows.push(['임금', '월 평균 ' + (d.wage.avg || '—')
                        + ' · 최근 지급 ' + (d.wage.last_paid_on || '—')
                        + ' · 체불 ' + (d.wage.unpaid ? '있음' : '없음')]);
                    rows.push(['주요 특이사항', d.opinion.notable || '—']);
                    rows.push(['개선 요구사항', d.opinion.improvements || '—']);
                    rows.push(['농가 건의사항', d.opinion.farm_requests || '—']);
                    rows.push(['향후 조치', '개선기한 ' + (d.actions.due_on || '—')
                        + ' · 담당 ' + (d.actions.assignee || '—')
                        + ' · 재점검 ' + (d.actions.recheck_on || '—')]);
                    rows.push(['보고 필요', (d.actions.report_city ? '지자체 ' : '') + (d.actions.report_immigration ? '출입국' : '')
                        || '없음']);
                    rows.push(['서명', d.signatures.map(function (s) {
                        return s.label + ' ' + (s.name || '—') + (s.image_url ? '' : ' (서명 없음)');
                    }).join('\n')]);

                    ndnDetailModal({
                        title: '근무상태 점검표 #' + d.id,
                        subtitle: d.worker + ' · ' + d.reviewed_at,
                        rows: rows,
                        // 제출본을 여기서 바로 받는다. 서명 이미지도 그 안에 들어간다.
                        links: [{ label: '제출용 PDF 내려받기', href: '{{ url('admin/work-reviews') }}/' + d.id + '/pdf' }]
                            .concat(d.signatures.filter(function (s) { return s.image_url; })
                                .map(function (s) { return { label: s.label + ' 서명', href: s.image_url }; })),
                    });
                })
                .catch(function () { ndnToast('점검표를 불러오지 못했습니다.', { type: 'error' }); });
        },
    });
</script>
@endsection
