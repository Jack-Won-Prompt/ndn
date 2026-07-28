@extends('admin.screens.layout')
@section('title', '계정 삭제 요청')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">계정 삭제 요청</h1>
            <p class="screen__sub">공개 페이지에서 접수된 삭제 요청 · 완료 처리 후 <strong>근로자 화면에서 해당 계정을 비활성/삭제</strong>하면 90일 후 민감정보가 자동 파기됩니다(§7-7)</p>
        </div>
    </div>

    <div class="ad-wrap">
        <table class="ad-table" id="ad-table">
            <thead>
                <tr>
                    <th style="width:56px">번호</th>
                    <th style="width:130px">이름</th>
                    <th>이메일(로그인 ID)</th>
                    <th>사유</th>
                    <th style="width:150px">신청일시</th>
                    <th style="width:110px">상태</th>
                    <th style="width:160px">처리</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $r)
                    <tr data-id="{{ $r['id'] }}" data-status="{{ $r['status'] }}">
                        <td class="c">{{ $r['id'] }}</td>
                        <td>{{ $r['name'] }}</td>
                        <td>{{ $r['email'] }}</td>
                        <td class="ad-reason">{{ $r['reason'] ?: '—' }}</td>
                        <td class="c">{{ $r['requested'] }}</td>
                        <td class="c">
                            <span class="ad-badge ad-badge--{{ $r['status'] }}">
                                {{ ['pending'=>'대기','completed'=>'완료','rejected'=>'거절'][$r['status']] ?? $r['status'] }}
                            </span>
                        </td>
                        <td class="c ad-actions">
                            @if ($r['status'] === 'pending')
                                <button type="button" class="ad-btn ad-btn--ok" data-act="completed">완료</button>
                                <button type="button" class="ad-btn ad-btn--no" data-act="rejected">거절</button>
                            @else
                                <span class="ad-done">{{ $r['processed'] ?? '처리됨' }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr id="ad-empty"><td colspan="7" class="ad-empty">접수된 계정 삭제 요청이 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <style>
        .ad-wrap{border:1px solid var(--mv2-border-default);border-radius:var(--mv2-r-lg);overflow:hidden;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .ad-table{width:100%;border-collapse:collapse;font-size:var(--mv2-fz-sm);}
        .ad-table thead th{text-align:left;background:var(--mv2-slate-25);color:var(--mv2-text-muted);font-weight:700;font-size:var(--mv2-fz-xs);padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);white-space:nowrap;}
        .ad-table tbody td{padding:11px 14px;border-bottom:1px solid var(--mv2-border-soft);color:var(--mv2-text-strong);vertical-align:middle;}
        .ad-table tbody tr:last-child td{border-bottom:0;}
        .ad-table td.c{text-align:center;}
        .ad-reason{color:var(--mv2-text-muted);max-width:280px;}
        .ad-empty{text-align:center;color:var(--mv2-text-faint);padding:34px 0;}
        .ad-badge{display:inline-block;padding:2px 9px;border-radius:100px;font-size:12px;font-weight:700;}
        .ad-badge--pending{background:#FFF3E0;color:#B45309;}
        .ad-badge--completed{background:#E7F3F1;color:#12695F;}
        .ad-badge--rejected{background:#FDECEC;color:#B42318;}
        .ad-btn{font-family:inherit;font-size:var(--mv2-fz-xs);font-weight:700;border:1px solid transparent;border-radius:var(--mv2-r-sm);padding:5px 12px;cursor:pointer;margin:0 2px;}
        .ad-btn--ok{background:var(--mv2-primary-500);color:#fff;}
        .ad-btn--ok:hover{background:var(--mv2-primary-600);}
        .ad-btn--no{background:#fff;color:var(--mv2-pill-err-fg);border-color:var(--mv2-border-default);}
        .ad-btn--no:hover{background:var(--mv2-pill-err-bg);border-color:var(--mv2-pill-err-fg);}
        .ad-done{color:var(--mv2-text-faint);font-size:12px;}
    </style>
@endsection

@section('script')
<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]').content;
        var BASE = '{{ url('admin/account-deletions') }}';

        function refreshBadge() {
            var n = document.querySelectorAll('#ad-table tr[data-status="pending"]').length;
            if (window.parent) window.parent.postMessage({ ndnBadge: { key: 'account-deletions', count: n } }, '*');
        }

        document.getElementById('ad-table').addEventListener('click', function (e) {
            var btn = e.target.closest('.ad-btn');
            if (!btn) return;
            var tr = btn.closest('tr[data-id]');
            var act = btn.getAttribute('data-act');
            var isDone = act === 'completed';
            var name = tr.children[1].textContent;
            ndnConfirm(name + ' 님의 삭제 요청을 ' + (isDone ? '완료 처리' : '거절') + '할까요?'
                + (isDone ? ' 완료 후 근로자 계정을 비활성/삭제하세요.' : ''),
                { title: isDone ? '삭제 요청 완료' : '삭제 요청 거절', okText: isDone ? '완료' : '거절', danger: !isDone })
                .then(function (ok) {
                    if (!ok) return;
                    fetch(BASE + '/' + tr.getAttribute('data-id') + '/process', {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                        body: JSON.stringify({ status: act }),
                    })
                        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                        .then(function (res) {
                            if (res.ok) {
                                ndnToast(isDone ? '완료 처리했습니다.' : '거절 처리했습니다.', { type: 'success' });
                                tr.setAttribute('data-status', act);
                                tr.querySelector('td:nth-child(6)').innerHTML =
                                    '<span class="ad-badge ad-badge--' + act + '">' + (isDone ? '완료' : '거절') + '</span>';
                                tr.querySelector('.ad-actions').innerHTML = '<span class="ad-done">방금</span>';
                                refreshBadge();
                            } else { ndnToast(res.j.message || '처리 실패', { type: 'error' }); }
                        });
                });
        });
    })();
</script>
@endsection
