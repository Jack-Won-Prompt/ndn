@extends('admin.screens.layout')
@section('title', '공지사항')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">공지사항</h1>
            <p class="screen__sub"><strong>FCM 푸시 + 인앱 알림</strong>으로 발송 · 근로자에게는 <strong>각자 언어로 자동 번역</strong> · 본문에 개인정보 금지(§7-3) · 되돌릴 수 없음</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="compose">발송</button>
        <button type="button" class="screen-tab" data-tab="history">발송 이력<span class="screen-tab__badge">{{ count($rows) }}</span></button>
    </div>

    <div data-tabpane="compose">
        @if (session('notice_sent') !== null)
            <div class="nt-ok">✅ 공지를 발송했습니다. (수신 대상 {{ session('notice_sent') }}명)</div>
        @endif

        <form class="nt-form" method="POST" action="{{ route('admin.notices.store') }}" id="nt-form">
            @csrf

            <div class="nt-field">
                <label for="nt-title">제목 <span class="nt-req">*</span></label>
                <input id="nt-title" type="text" name="title" value="{{ old('title') }}" maxlength="120" required>
                @error('title')<p class="nt-err">{{ $message }}</p>@enderror
            </div>

            <div class="nt-field">
                <label for="nt-body">내용 <span class="nt-req">*</span></label>
                <textarea id="nt-body" name="body" rows="6" maxlength="4000" required>{{ old('body') }}</textarea>
                <p class="nt-help">이름·전화·여권번호를 적으면 발송이 거부됩니다. 푸시는 잠금화면에 그대로 뜹니다(§7-3).</p>
                @error('body')<p class="nt-err">{{ $message }}</p>@enderror
            </div>

            <div class="nt-row">
                <div class="nt-field">
                    <label for="nt-target">대상 <span class="nt-req">*</span></label>
                    <select id="nt-target" name="target">
                        @foreach ($targetOptions as $v => $l)
                            <option value="{{ $v }}" @selected(old('target') === $v)>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="nt-field" id="nt-value-wrap" hidden>
                    <label for="nt-value">대상 값 <span class="nt-req">*</span></label>
                    <select id="nt-value" name="target_value"></select>
                </div>
            </div>
            @error('target')<p class="nt-err">{{ $message }}</p>@enderror
            @error('target_value')<p class="nt-err">{{ $message }}</p>@enderror

            {{-- '전체' 는 담당자 앱까지 간다. 실수로 누르면 가장 비싼 선택이라 미리 알린다. --}}
            <div class="nt-warn" id="nt-everyone" hidden>
                근로자뿐 아니라 <b>담당자 앱을 쓰는 {{ $appUsers }}명</b>에게도 함께 갑니다.
                담당자는 한국어 원문 그대로 받습니다.
            </div>

            {{-- 근로자 선택 --}}
            <div class="nt-pick" id="nt-pick" hidden>
                <div class="nt-pick__head">
                    <b>받을 근로자 고르기</b>
                    <span class="nt-chip" id="nt-count">0명 선택</span>
                    <input type="search" id="nt-find" placeholder="이름·국적으로 찾기">
                    <button type="button" class="nt-mini" id="nt-all">보이는 사람 모두</button>
                    <button type="button" class="nt-mini" id="nt-none">선택 해제</button>
                </div>
                <p class="nt-help" style="margin:0 0 8px">
                    <b>앱 미설치</b> 표시가 있는 사람은 푸시를 받지 못합니다. 앱을 열면 인앱 알림함에서 볼 수 있습니다.
                </p>
                <div class="nt-list">
                    @forelse ($workers as $w)
                        <label class="nt-worker" data-search="{{ $w['name'] }} {{ $w['nationality'] }}">
                            <input type="checkbox" name="worker_ids[]" value="{{ $w['id'] }}">
                            <span class="nt-worker__name">{{ $w['name'] }}</span>
                            <span class="nt-worker__meta">{{ $w['nationality'] }} · {{ $w['locale'] }}</span>
                            @unless ($w['app'])<span class="nt-noapp">앱 미설치</span>@endunless
                        </label>
                    @empty
                        <div class="nt-empty">재직 중인 근로자가 없습니다.</div>
                    @endforelse
                </div>
                @error('worker_ids')<p class="nt-err">{{ $message }}</p>@enderror
            </div>

            <div class="nt-actions">
                <button type="submit" class="nt-btn" id="nt-send">공지 발송</button>
                <span class="nt-hint">발송 즉시 푸시됩니다. 되돌릴 수 없습니다.</span>
            </div>
        </form>
    </div>

    <div data-tabpane="history" hidden>
        <div id="grid-notices"></div>
        <p class="nt-listhint">
            보낸 공지는 <strong>고칠 수 없습니다</strong> — 받는 사람에게는 이미 나갔으므로,
            여기 적힌 것과 실제로 받은 내용이 달라지면 안 됩니다. 내용을 바로잡으려면 새 공지를 보내세요.
        </p>
    </div>

    <style>
        .nt-listhint{font-size:12px;color:var(--mv2-text-faint);margin:10px 2px 0;line-height:1.7;}
        .nt-ok{background:#E7F3F1;border:1px solid #B9E0D9;color:#12695F;padding:12px 15px;border-radius:10px;margin-bottom:16px;font-size:14px;}
        .nt-form{max-width:820px;background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:20px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .nt-field{margin-bottom:15px;}
        .nt-field label{display:block;font-size:13px;font-weight:700;color:var(--mv2-text-strong);margin-bottom:6px;}
        .nt-field input, .nt-field textarea, .nt-field select{width:100%;box-sizing:border-box;border:1px solid var(--mv2-border-default);border-radius:9px;padding:9px 11px;font-family:inherit;font-size:14px;}
        .nt-field textarea{resize:vertical;}
        .nt-field input:focus, .nt-field textarea:focus, .nt-field select:focus{outline:none;border-color:var(--mv2-primary-500);box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        /* display:flex/block 을 쓰는 칸은 hidden 속성만으로 숨겨지지 않는다. */
        .nt-field[hidden]{display:none;}
        .nt-row{display:flex;gap:14px;}
        .nt-row .nt-field{flex:1;}
        .nt-req{color:#E5484D;}
        .nt-err{color:#B42318;font-size:13px;margin:6px 0 0;}
        .nt-help{font-size:12px;color:var(--mv2-text-muted);margin:6px 0 0;line-height:1.6;}
        .nt-warn{background:#FEF3C7;color:#8a6d00;border-radius:10px;padding:11px 13px;font-size:13px;line-height:1.6;margin-bottom:15px;}
        .nt-warn[hidden]{display:none;}
        .nt-actions{margin-top:8px;display:flex;align-items:center;gap:14px;}
        .nt-btn{font-family:inherit;font-size:14px;font-weight:700;color:#fff;background:var(--mv2-primary-500);border:0;border-radius:10px;padding:11px 22px;cursor:pointer;}
        .nt-btn:hover{background:var(--mv2-primary-600);}
        .nt-btn:disabled{background:var(--mv2-slate-25);color:var(--mv2-text-faint);cursor:not-allowed;}
        .nt-hint{font-size:12px;color:var(--mv2-text-muted);}

        /* 근로자 고르기 */
        .nt-pick{border:1px solid var(--mv2-border-default);border-radius:10px;padding:14px;margin-bottom:15px;background:var(--mv2-slate-25);}
        .nt-pick[hidden]{display:none;}
        .nt-pick__head{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-bottom:8px;}
        .nt-pick__head b{font-size:13px;color:var(--mv2-text-strong);}
        .nt-pick__head input[type=search]{flex:1;min-width:150px;font-family:inherit;font-size:13px;padding:6px 10px;border:1px solid var(--mv2-border-default);border-radius:8px;}
        .nt-chip{font-size:11px;font-weight:700;background:#fff;color:var(--mv2-text-muted);border-radius:100px;padding:3px 10px;}
        .nt-mini{font-family:inherit;font-size:11px;font-weight:700;color:var(--mv2-text-muted);background:#fff;border:1px solid var(--mv2-border-default);border-radius:8px;padding:5px 10px;cursor:pointer;}
        .nt-mini:hover{border-color:var(--mv2-text-strong);color:var(--mv2-text-strong);}
        .nt-list{max-height:280px;overflow-y:auto;background:#fff;border:1px solid var(--mv2-border-soft);border-radius:8px;padding:6px;}
        .nt-worker{display:flex;align-items:center;gap:8px;padding:7px 9px;border-radius:6px;cursor:pointer;font-size:13px;}
        .nt-worker:hover{background:var(--mv2-slate-25);}
        .nt-worker.is-off{display:none;}
        .nt-worker__name{font-weight:700;color:var(--mv2-text-strong);}
        .nt-worker__meta{font-size:11px;color:var(--mv2-text-muted);}
        .nt-noapp{font-size:10px;font-weight:700;background:#FEF3C7;color:#8a6d00;border-radius:100px;padding:2px 8px;margin-left:auto;}

    </style>
@endsection

@section('wwgrid')
<script>
    // 보낸 공지는 **읽기 전용**이다. 받는 사람에게는 이미 나갔으므로 여기 적힌 것과
    // 실제로 받은 내용이 달라지면 안 된다. 그래서 [신규 행]·[변경 저장] 을 두지 않는다.
    wwConsole({
        el: 'grid-notices',
        title: '공지사항',
        data: @json($rows, JSON_UNESCAPED_UNICODE),
        columns: [
            { header: '번호', name: 'id', width: 60, align: 'center', sortable: true },
            { header: '제목', name: 'title', width: 300, sortable: true },
            { header: '대상', name: 'target', width: 170, align: 'center', sortable: true },
            { header: '수신', name: 'count_label', width: 80, align: 'center', sortable: true },
            // 골라 보낸 공지만 이름이 남는다. 나머지는 건수로 충분하다.
            { header: '받은 사람', name: 'who', width: 280 },
            { header: '발송일시', name: 'sent', width: 150, align: 'center', sortable: true },
        ],
    });
</script>
@endsection

@section('script')
<script>
    (function () {
        var NAT = @json(collect($nationalityOptions)->map(fn ($l, $c) => [$c, $l])->values());
        var STA = @json(collect($statusOptions)->map(fn ($l, $c) => [$c, $l])->values());

        var target = document.getElementById('nt-target');
        var wrap = document.getElementById('nt-value-wrap');
        var value = document.getElementById('nt-value');
        var pick = document.getElementById('nt-pick');
        var everyone = document.getElementById('nt-everyone');

        // 대상에 따라 필요한 칸만 연다. 다 보여 주면 무엇을 채워야 하는지 흐려진다.
        function sync() {
            var t = target.value;
            var needsValue = (t === 'nationality' || t === 'status');

            wrap.hidden = ! needsValue;
            if (needsValue) {
                var opts = (t === 'nationality') ? NAT : STA;
                value.innerHTML = opts.map(function (o) {
                    return '<option value="' + o[0] + '">' + o[1] + '</option>';
                }).join('');
            } else {
                value.innerHTML = '';
            }

            pick.hidden = (t !== 'selected');
            everyone.hidden = (t !== 'everyone');
        }
        target.addEventListener('change', sync);
        sync();

        /* ---------- 근로자 고르기 ---------- */
        function checked() { return pick.querySelectorAll('input[type=checkbox]:checked'); }

        function refreshCount() {
            document.getElementById('nt-count').textContent = checked().length + '명 선택';
        }

        pick.addEventListener('change', refreshCount);

        // 이름으로 좁히기 — 고른 사람은 검색어와 안 맞아도 숨기지 않는다.
        // 안 보이는 채로 발송되면 누구에게 갔는지 모른다.
        document.getElementById('nt-find').addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            [].forEach.call(pick.querySelectorAll('.nt-worker'), function (row) {
                var hit = ! q || row.getAttribute('data-search').toLowerCase().indexOf(q) !== -1;
                row.classList.toggle('is-off', ! hit && ! row.querySelector('input').checked);
            });
        });

        document.getElementById('nt-all').addEventListener('click', function () {
            [].forEach.call(pick.querySelectorAll('.nt-worker:not(.is-off) input'), function (i) { i.checked = true; });
            refreshCount();
        });

        document.getElementById('nt-none').addEventListener('click', function () {
            [].forEach.call(pick.querySelectorAll('input[type=checkbox]'), function (i) { i.checked = false; });
            refreshCount();
        });

        refreshCount();

        /* ---------- 발송 확인 ---------- */
        // 되돌릴 수 없다. 몇 명에게 가는지 눈으로 보고 누르게 한다.
        document.getElementById('nt-form').addEventListener('submit', function (e) {
            if (this.dataset.confirmed === '1') return;
            e.preventDefault();

            var t = target.value;
            var who = ({
                everyone: '근로자 전체와 담당자 앱 사용자 {{ $appUsers }}명',
                all: '재직 중인 근로자 전체',
                nationality: '국적 ' + (value.options[value.selectedIndex] || {}).text + ' 근로자',
                status: '상태 ' + (value.options[value.selectedIndex] || {}).text + ' 근로자',
                selected: '고른 근로자 ' + checked().length + '명',
            })[t] || '대상';

            if (t === 'selected' && checked().length === 0) {
                ndnToast('보낼 근로자를 한 명 이상 고르세요.', { type: 'error' });
                return;
            }

            var form = this;
            ndnConfirm(who + '에게 공지를 보냅니다. 발송하면 되돌릴 수 없습니다.',
                { title: '공지 발송', okText: '발송' })
                .then(function (ok) {
                    if (! ok) return;
                    form.dataset.confirmed = '1';
                    document.getElementById('nt-send').disabled = true;
                    form.submit();
                });
        });
    })();
</script>
@endsection
