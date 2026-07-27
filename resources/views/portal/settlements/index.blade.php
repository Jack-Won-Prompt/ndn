@extends('portal.layout', ['active' => 'settlements'])
@section('title', '정착 처리')

@section('body')
    <div class="ps-head">
        <h1>정착 서비스 처리</h1>
        <p>본사에서 배정한 건만 표시됩니다. 상세에서 처리 상태를 갱신하고 증빙을 업로드하세요.</p>
    </div>

    <div class="ps-card">
        <table class="ps-table">
            <thead>
                <tr>
                    <th style="width:64px">번호</th>
                    <th>근로자</th>
                    <th style="width:90px">유형</th>
                    <th style="width:110px">상태</th>
                    <th style="width:150px">SLA 기한</th>
                    <th style="width:80px">증빙</th>
                    <th style="width:90px"></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $s)
                    <tr>
                        <td class="c">#{{ $s->id }}</td>
                        <td>{{ $s->worker?->name ?? '—' }}</td>
                        <td class="c">{{ $s->type->label() }}</td>
                        <td class="c"><span class="ps-badge ps-badge--{{ $s->status->value }}">{{ $s->status->label() }}</span></td>
                        <td class="c {{ $s->isOverdue() ? 'ps-over' : '' }}">
                            {{ $s->sla_due_at ? \App\Shared\Support\LocalTime::format($s->sla_due_at, 'Y-m-d') : '—' }}
                            {{ $s->isOverdue() ? '· 지연' : '' }}
                        </td>
                        <td class="c">{{ $s->documents_count }}건</td>
                        <td class="c"><a class="ps-link" href="{{ route('portal.settlements.show', $s->id) }}">처리 →</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="ps-empty">배정된 정착 서비스 건이 없습니다.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('head')
    <style>
        .ps-head h1{font-size:22px;font-weight:800;margin:0 0 4px;}
        .ps-head p{margin:0 0 18px;color:#6B7280;font-size:14px;}
        .ps-card{background:#fff;border:1px solid #E3E6EA;border-radius:12px;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .ps-table{width:100%;border-collapse:collapse;font-size:14px;}
        .ps-table th{text-align:left;background:#F8FAFA;color:#6B7280;font-weight:700;font-size:12px;padding:11px 14px;border-bottom:1px solid #EEF1F4;}
        .ps-table td{padding:12px 14px;border-bottom:1px solid #F1F4F6;color:#1B1E24;}
        .ps-table tr:last-child td{border-bottom:0;}
        .ps-table td.c{text-align:center;}
        .ps-empty{text-align:center;color:#9AA1AC;padding:34px 0;}
        .ps-over{color:#B42318;font-weight:700;}
        .ps-link{color:#1E9C92;font-weight:700;text-decoration:none;}
        .ps-badge{display:inline-block;padding:2px 9px;border-radius:100px;font-size:12px;font-weight:700;}
        .ps-badge--assigned{background:#E7F0FF;color:#1D4ED8;}
        .ps-badge--processing{background:#FFF3E0;color:#B45309;}
        .ps-badge--done{background:#E7F3F1;color:#12695F;}
        .ps-badge--received,.ps-badge--reviewing{background:#EEF1F4;color:#4B5563;}
    </style>
    @endpush
@endsection
