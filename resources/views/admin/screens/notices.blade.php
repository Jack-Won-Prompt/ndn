@extends('admin.screens.layout')
@section('title', '공지사항')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">공지사항</h1>
            <p class="screen__sub">근로자에게 <strong>FCM 푸시 + 인앱 알림</strong>으로 공지 발송 · 근로자 언어로 <strong>자동 번역</strong> · 본문에 개인정보 금지(§7-3)</p>
        </div>
    </div>

    <div class="screen-tabs">
        <button type="button" class="screen-tab is-active" data-tab="compose">발송</button>
        <button type="button" class="screen-tab" data-tab="history">발송 이력</button>
    </div>

    <div data-tabpane="compose">
        @if (session('notice_sent') !== null)
            <div class="nt-ok">✅ 공지를 발송했습니다. (수신 대상 {{ session('notice_sent') }}명)</div>
        @endif
        <form class="nt-form" method="POST" action="{{ route('admin.notices.store') }}">
            @csrf
            <div class="nt-field">
                <label for="nt-title">제목 <span class="nt-req">*</span></label>
                <input id="nt-title" type="text" name="title" value="{{ old('title') }}" maxlength="120" required>
                @error('title')<p class="nt-err">{{ $message }}</p>@enderror
            </div>
            <div class="nt-field">
                <label for="nt-body">내용 <span class="nt-req">*</span></label>
                <textarea id="nt-body" name="body" rows="6" maxlength="4000" required>{{ old('body') }}</textarea>
                @error('body')<p class="nt-err">{{ $message }}</p>@enderror
            </div>
            <div class="nt-row">
                <div class="nt-field">
                    <label for="nt-target">대상</label>
                    <select id="nt-target" name="target">
                        <option value="all">전체 근로자(재직)</option>
                        <option value="nationality">국적별</option>
                        <option value="status">상태별</option>
                    </select>
                </div>
                <div class="nt-field" id="nt-value-wrap" hidden>
                    <label for="nt-value">대상 값</label>
                    <select id="nt-value" name="target_value"></select>
                </div>
            </div>
            @error('target_value')<p class="nt-err">{{ $message }}</p>@enderror
            <div class="nt-actions">
                <button type="submit" class="nt-btn">공지 발송</button>
                <span class="nt-hint">발송 즉시 대상 근로자에게 푸시됩니다. 되돌릴 수 없습니다.</span>
            </div>
        </form>
    </div>

    <div data-tabpane="history" hidden>
        <div class="nt-wrap">
            <table class="nt-table">
                <thead>
                    <tr><th style="width:56px">번호</th><th>제목</th><th style="width:160px">대상</th><th style="width:90px">수신</th><th style="width:150px">발송일시</th></tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        <tr>
                            <td class="c">{{ $r['id'] }}</td>
                            <td>{{ $r['title'] }}</td>
                            <td class="c">{{ $r['target'] }}</td>
                            <td class="c">{{ $r['count'] }}명</td>
                            <td class="c">{{ $r['sent'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="nt-empty">발송한 공지가 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <style>
        .nt-ok{background:#E7F3F1;border:1px solid #B9E0D9;color:#12695F;padding:12px 15px;border-radius:10px;margin-bottom:16px;font-size:14px;}
        .nt-form{max-width:720px;background:#fff;border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);padding:20px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .nt-field{margin-bottom:15px;}
        .nt-field label{display:block;font-size:13px;font-weight:700;color:var(--mv2-text-strong);margin-bottom:6px;}
        .nt-field input, .nt-field textarea, .nt-field select{width:100%;box-sizing:border-box;border:1px solid var(--mv2-border-default);border-radius:9px;padding:9px 11px;font-family:inherit;font-size:14px;}
        .nt-field textarea{resize:vertical;}
        .nt-field input:focus, .nt-field textarea:focus, .nt-field select:focus{outline:none;border-color:var(--mv2-primary-500);box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        .nt-row{display:flex;gap:14px;}
        .nt-row .nt-field{flex:1;}
        .nt-req{color:#E5484D;}
        .nt-err{color:#B42318;font-size:13px;margin:6px 0 0;}
        .nt-actions{margin-top:8px;display:flex;align-items:center;gap:14px;}
        .nt-btn{font-family:inherit;font-size:14px;font-weight:700;color:#fff;background:var(--mv2-primary-500);border:0;border-radius:10px;padding:11px 22px;cursor:pointer;}
        .nt-btn:hover{background:var(--mv2-primary-600);}
        .nt-hint{font-size:12px;color:var(--mv2-text-muted);}
        .nt-wrap{border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);overflow:hidden;background:#fff;}
        .nt-table{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);}
        .nt-table th{text-align:left;background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-weight:700;font-size:var(--mv2-fz-xs);padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);}
        .nt-table td{padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);}
        .nt-table td.c{text-align:center;}
        .nt-empty{text-align:center;color:var(--mv2-text-faint);padding:30px 0;}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var NAT = @json(collect(App\Domains\Recruitment\Enums\Nationality::adminOptions())->map(fn ($l, $c) => [$c, $l])->values());
        var STA = [['active','재직'],['inactive','비활성'],['returned','귀국']];
        var target = document.getElementById('nt-target');
        var wrap = document.getElementById('nt-value-wrap');
        var value = document.getElementById('nt-value');
        function sync() {
            var t = target.value;
            if (t === 'all') { wrap.hidden = true; value.innerHTML = ''; return; }
            var opts = (t === 'nationality') ? NAT : STA;
            value.innerHTML = opts.map(function (o) { return '<option value="' + o[0] + '">' + o[1] + '</option>'; }).join('');
            wrap.hidden = false;
        }
        target.addEventListener('change', sync);
        sync();
    })();
</script>
@endsection
