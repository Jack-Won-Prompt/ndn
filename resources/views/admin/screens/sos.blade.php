@extends('admin.screens.layout')
@section('title', '긴급 SOS')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">긴급 SOS</h1>
            <p class="screen__sub">근로자가 앱에서 누른 긴급 요청 · <strong>미확인이 위로, 오래 방치된 것부터</strong> · 좌표는 누른 그 순간 1회분입니다</p>
        </div>
    </div>

    @if ($openCount > 0)
        <div class="sos-alert">
            <b>미확인 {{ $openCount }}건</b> — 아직 아무도 확인하지 않은 긴급 요청이 있습니다.
        </div>
    @endif

    <div class="sos-wrap">
        <table class="sos-table">
            <thead>
                <tr>
                    <th style="width:88px">상태</th>
                    <th style="width:150px">근로자</th>
                    <th style="width:180px">소속</th>
                    <th style="width:150px">발신 시각</th>
                    <th style="width:110px">경과·대응</th>
                    <th style="width:220px">좌표</th>
                    <th>확인</th>
                    <th style="width:150px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr data-id="{{ $r['id'] }}" class="sos-row sos-row--{{ $r['status'] }}">
                        <td class="c">
                            <span class="sos-badge sos-badge--{{ $r['status'] }}">{{ $r['status_label'] }}</span>
                        </td>
                        <td>
                            <b>{{ $r['worker'] }}</b>
                            <span class="sos-dim">{{ $r['nationality'] }}</span>
                        </td>
                        <td>{{ $r['city'] }} · {{ $r['farm'] }}</td>
                        <td class="c">{{ $r['alerted_at'] }}</td>
                        <td class="c {{ $r['status'] === 'open' && $r['minutes'] >= 30 ? 'sos-late' : '' }}">
                            {{ $r['elapsed'] }}
                        </td>
                        <td class="c sos-coord">
                            @if ($r['map_url'])
                                <span class="sos-dim">{{ $r['coords'] }}</span>
                                <a class="sos-map" href="{{ $r['map_url'] }}" target="_blank" rel="noopener">지도</a>
                            @else
                                <span class="sos-dim">좌표 없음</span>
                            @endif
                        </td>
                        <td>
                            @if ($r['status'] === 'open')
                                <span class="sos-dim">—</span>
                            @else
                                {{ $r['acknowledged_by'] }} <span class="sos-dim">{{ $r['acknowledged_at'] }}</span>
                            @endif
                        </td>
                        <td class="c">
                            @if ($r['status'] === 'open')
                                <button type="button" class="sos-btn sos-btn--primary"
                                        data-act="acknowledged" data-worker="{{ $r['worker'] }}">확인 처리</button>
                            @elseif ($r['status'] === 'acknowledged')
                                <button type="button" class="sos-btn"
                                        data-act="closed" data-worker="{{ $r['worker'] }}">종료 처리</button>
                            @else
                                <span class="sos-dim">처리 완료</span>
                            @endif
                        </td>
                    </tr>
                    @if ($r['note'] !== '')
                        <tr class="sos-noterow"><td colspan="8"><span class="sos-dim">메모</span> {{ $r['note'] }}</td></tr>
                    @endif
                @empty
                    <tr><td colspan="8" class="sos-empty">접수된 SOS 가 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <style>
        .sos-alert{background:#FDECEC;border:1px solid #F5C2C0;color:#8A1F1C;border-radius:var(--mv2-r-lg);
            padding:13px 16px;margin-bottom:14px;font-size:var(--mv2-fz-sm);line-height:1.6;}
        .sos-alert b{font-weight:800;}
        .sos-wrap{border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .sos-table{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);}
        .sos-table thead th{text-align:left;background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-weight:700;font-size:var(--mv2-fz-xs);padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);white-space:nowrap;}
        .sos-table tbody td{padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);color:var(--mv2-text-strong);}
        .sos-table td.c{text-align:center;}
        .sos-row--open{background:#FFFBFB;}
        .sos-noterow td{padding-top:0;padding-bottom:11px;font-size:var(--mv2-fz-xs);color:var(--mv2-text-muted);}
        .sos-dim{color:var(--mv2-text-faint);font-size:var(--mv2-fz-xs);}
        .sos-late{color:#B3261E;font-weight:800;}
        .sos-empty{text-align:center;color:var(--mv2-text-faint);padding:34px 0;}
        .sos-badge{display:inline-block;padding:2px 10px;border-radius:100px;font-size:12px;font-weight:800;}
        .sos-badge--open{background:#FDECEC;color:#8A1F1C;}
        .sos-badge--acknowledged{background:#FFF4E0;color:#8A5A00;}
        .sos-badge--closed{background:#F1F3F7;color:#6B7280;}
        /* 좌표와 [지도] 가 갈라져 두 줄이 되지 않게 한 덩어리로 둔다 */
        .sos-coord{white-space:nowrap;}
        .sos-map{display:inline-block;margin-left:6px;padding:2px 9px;border-radius:100px;font-size:12px;font-weight:700;
            background:var(--mv2-slate-25);border:1px solid var(--mv2-border-default);color:var(--mv2-text-strong);text-decoration:none;}
        .sos-map:hover{border-color:var(--mv2-text-strong);}
        .sos-btn{font-family:inherit;font-size:var(--mv2-fz-xs);font-weight:700;border:1px solid var(--mv2-border-default);background:#fff;border-radius:var(--mv2-r-sm);padding:7px 15px;cursor:pointer;white-space:nowrap;}
        .sos-btn--primary{background:var(--mv2-primary-500);color:#fff;border-color:transparent;}
        .sos-btn--primary:hover{background:var(--mv2-primary-600);}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/sos') }}';

        document.querySelector('.sos-table').addEventListener('click', function (e) {
            var btn = e.target.closest('[data-act]');
            if (!btn) return;

            var id = btn.closest('tr[data-id]').getAttribute('data-id');
            var act = btn.getAttribute('data-act');
            var worker = btn.getAttribute('data-worker');
            var label = act === 'acknowledged' ? '확인 처리' : '종료 처리';
            var msg = act === 'acknowledged'
                ? worker + ' 님의 긴급 요청을 확인 처리합니다. 확인한 사람과 시각이 기록됩니다.'
                : worker + ' 님의 긴급 요청을 종료 처리합니다.';

            ndnConfirm(msg, { title: label, okText: label }).then(function (ok) {
                if (!ok) return;
                btn.disabled = true;
                fetch(BASE + '/' + id + '/status', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ status: act }),
                })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                    .then(function (res) {
                        if (res.ok && res.j.ok) {
                            ndnToast(label + '했습니다.', { type: 'success' });
                            setTimeout(function () { location.reload(); }, 700);
                        } else {
                            btn.disabled = false;
                            ndnToast(res.j.message || '처리하지 못했습니다.', { type: 'error' });
                        }
                    })
                    .catch(function () { btn.disabled = false; ndnToast('처리하지 못했습니다.', { type: 'error' }); });
            });
        });
    })();
</script>
@endsection
