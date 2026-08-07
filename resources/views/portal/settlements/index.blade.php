@extends('portal.layout', ['active' => 'settlements'])
@section('title', '정착 처리')

@section('body')
    <div class="nd-pagehead nd-pagehead--row">
        <div>
            <h1>정착 서비스 처리</h1>
            <p>본사에서 배정한 건만 보입니다. 상세에서 처리 상태를 갱신하고 증빙을 올리세요.</p>
        </div>
    </div>

    <div class="nd-tablewrap nd-tablewrap--dense">
        <table class="nd-table">
            <thead>
                <tr>
                    <th style="width:70px">번호</th>
                    <th>근로자</th>
                    <th style="width:96px">유형</th>
                    <th style="width:116px">상태</th>
                    <th style="width:170px">SLA 기한</th>
                    <th style="width:80px">증빙</th>
                    <th style="width:96px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $s)
                    <tr>
                        <td class="nd-c">#{{ $s->id }}</td>
                        <td>{{ $s->worker?->name ?? '—' }}</td>
                        <td class="nd-c">{{ $s->type->label() }}</td>
                        <td class="nd-c">
                            <span class="nd-badge nd-badge--{{ $s->status->value === 'done' ? 'ok' : ($s->status->value === 'processing' ? 'warn' : 'mute') }}">
                                {{ $s->status->label() }}
                            </span>
                        </td>
                        {{-- 지연된 건은 눈에 띄어야 한다. SLA 를 넘긴 건이 목록에 섞여 있으면 놓친다. --}}
                        <td class="nd-c {{ $s->isOverdue() ? 'nd-over' : '' }}">
                            {{ $s->sla_due_at ? \App\Shared\Support\LocalTime::format($s->sla_due_at, 'Y-m-d') : '—' }}
                            {{ $s->isOverdue() ? ' · 지연' : '' }}
                        </td>
                        <td class="nd-c">{{ $s->documents_count }}건</td>
                        <td class="nd-c">
                            <a class="nd-btn nd-btn--line nd-btn--sm" href="{{ route('portal.settlements.show', $s->id) }}">처리</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="nd-empty">배정된 정착 서비스 건이 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

@endsection
