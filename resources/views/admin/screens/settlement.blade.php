@extends('admin.screens.layout')
@section('title', '정착 처리보드')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">정착 처리보드</h1>
            <p class="screen__sub">칸반: 접수 → 서류 검수 → 대리점 전달 → 처리 중 → 완료 · SLA 지연은 붉게 표시</p>
        </div>
    </div>

    <div class="kanban">
        @foreach ($stages as $stage)
            @php $cards = $all->where('status', $stage); @endphp
            <div class="kanban__col">
                <div class="kanban__head">
                    <span>{{ $stage->label() }}</span>
                    <span class="kanban__count">{{ $cards->count() }}</span>
                </div>
                <div class="kanban__body">
                    @forelse ($cards as $s)
                        <div class="kcard {{ $s->isOverdue() ? 'kcard--overdue' : '' }}"
                             @if ($s->worker) data-worker="{{ $s->worker->id }}" @endif>
                            <div class="kcard__top">
                                <span class="mv2-pill">{{ $s->type->label() }}</span>
                                <span class="kcard__id">#{{ $s->id }}</span>
                            </div>
                            <div class="kcard__worker">{{ $s->worker?->name ?? '근로자 미지정' }}</div>
                            @if ($s->assigned_agency_id)
                                <div class="kcard__meta">대리점 #{{ $s->assigned_agency_id }}</div>
                            @endif
                            @if ($s->sla_due_at)
                                <div class="kcard__meta {{ $s->isOverdue() ? 'is-overdue' : '' }}">
                                    SLA {{ \App\Shared\Support\LocalTime::format($s->sla_due_at, 'm-d') }}{{ $s->isOverdue() ? ' · 지연' : '' }}
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="kanban__empty">—</div>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>

    <style>
        .kanban { display: grid; grid-template-columns: repeat(5, minmax(0,1fr)); gap: 12px; align-items: start; }
        .kanban__col { background: var(--mv2-slate-50); border: 1px solid var(--mv2-border-soft); border-radius: var(--mv2-r-md); overflow: hidden; }
        .kanban__head { display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; font-size: 13px; font-weight: 700; color: var(--mv2-text-strong); border-bottom: 1px solid var(--mv2-border-soft); background: #fff; }
        .kanban__count { font-size: 11px; font-weight: 600; color: var(--mv2-text-muted); background: var(--mv2-slate-100); border-radius: 100px; padding: 1px 8px; }
        .kanban__body { padding: 10px; display: flex; flex-direction: column; gap: 8px; min-height: 60px; }
        .kanban__empty { text-align: center; color: var(--mv2-text-faint); font-size: 12px; padding: 8px 0; }
        .kcard { background: #fff; border: 1px solid var(--mv2-border-default); border-radius: var(--mv2-r-sm); padding: 10px; box-shadow: var(--mv2-shadow-xs); }
        .kcard[data-worker] { cursor: pointer; transition: border-color .15s, box-shadow .15s; }
        .kcard[data-worker]:hover { border-color: var(--mv2-primary-400); box-shadow: 0 2px 8px rgba(30,156,146,.15); }
        .kcard--overdue { border-color: #F1B4B4; background: #FEF6F6; }
        .kcard__top { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        .kcard__id { font-size: 11px; color: var(--mv2-text-faint); }
        .kcard__worker { font-size: 13px; font-weight: 600; color: var(--mv2-text-strong); }
        .kcard__meta { font-size: 11px; color: var(--mv2-text-muted); margin-top: 4px; }
        .kcard__meta.is-overdue { color: #B42318; font-weight: 600; }
        @media (max-width: 1100px) { .kanban { grid-template-columns: repeat(2, 1fr); } }
    </style>
@endsection

@section('script')
<script>
    (function () {
        document.querySelector('.kanban').addEventListener('click', function (e) {
            var card = e.target.closest('.kcard[data-worker]');
            if (!card) return;
            var id = card.getAttribute('data-worker');
            fetch('{{ url('admin/screen/workers') }}/' + id + '?format=json', { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : Promise.reject(); })
                .then(function (d) {
                    var rows = [
                        ['이름', d.name], ['국적', d.nationality], ['언어', d.locale],
                        ['상태', d.status], ['소속', (d.city || '—') + ' · ' + (d.farm || '—')],
                    ];
                    if (d.arrival) { rows.push(['입국', d.arrival.status + (d.arrival.flight_no ? ' · ' + d.arrival.flight_no : '')]); }
                    var ivs = d.interviews || [];
                    rows.push(['생활 점검', ivs.length ? (ivs.length + '건 · 최근 ' + ivs[0].date + '(' + ivs[0].risk + ')') : '없음']);
                    rows.push(['등록일', d.created || '—']);
                    ndnDetailModal({
                        title: '근로자 정보', subtitle: d.name + ' · ' + d.nationality, rows: rows,
                        note: '개인정보 열람은 감사 로그에 기록됩니다.',
                    });
                })
                .catch(function () { ndnToast('근로자 정보를 불러오지 못했습니다.', { type: 'error' }); });
        });
    })();
</script>
@endsection
