@extends('demand.layout')
@section('title', '수요 신청')

@section('content')
    <div class="dp-head">
        <h1>수요 신청</h1>
        <a href="{{ route('demand.create') }}" class="dp-btn">+ 새 수요 신청</a>
    </div>

    <div class="dp-card">
        <table class="dq-table">
            <thead>
                <tr>
                    <th style="width:60px">번호</th>
                    <th>농가</th>
                    <th style="width:90px">국적</th>
                    <th style="width:70px">인원</th>
                    <th>품목</th>
                    <th style="width:180px">기간</th>
                    <th style="width:110px">상태</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($demands as $demand)
                    <tr onclick="location.href='{{ route('demand.show', $demand) }}'">
                        <td class="c">{{ $demand->id }}</td>
                        <td>{{ $demand->farm?->name ?? '—' }}</td>
                        <td class="c">{{ $demand->nationality }}</td>
                        <td class="c">{{ $demand->headcount }}명</td>
                        <td>{{ $demand->crop }}</td>
                        <td class="c">{{ $demand->period_start?->format('Y-m-d') }} ~ {{ $demand->period_end?->format('Y-m-d') }}</td>
                        <td class="c"><span class="dq-badge dq-badge--{{ $demand->status->value }}">{{ $demand->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="dq-empty">등록된 수요 신청이 없습니다. [+ 새 수요 신청]으로 시작하세요.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="dq-pager">{{ $demands->links() }}</div>

    <style>
        .dq-table{width:100%;border-collapse:collapse;font-size:14px;}
        .dq-table thead th{text-align:left;background:#F7F9FA;color:#6B7280;font-weight:700;font-size:12px;padding:11px 14px;border-bottom:1px solid #EAEDF0;white-space:nowrap;}
        .dq-table tbody td{padding:12px 14px;border-bottom:1px solid #EEF1F4;}
        .dq-table tbody tr:last-child td{border-bottom:0;}
        .dq-table tbody tr{cursor:pointer;}
        .dq-table tbody tr:hover{background:#F7F9FA;}
        .dq-table td.c{text-align:center;}
        .dq-empty{text-align:center;color:#9AA1AC;padding:38px 0;cursor:default;}
        .dq-badge{font-size:11px;font-weight:700;border-radius:100px;padding:2px 9px;white-space:nowrap;}
        .dq-badge--draft{background:#F0F2F4;color:#6B7280;}
        .dq-badge--submitted{background:#E9F6F4;color:#178578;}
        .dq-badge--aggregated{background:#E6F0FB;color:#1D65B8;}
        .dq-badge--letter_issued{background:#E7F6EC;color:#1B7F43;}
        .dq-badge--rejected{background:#FDECEC;color:#B42318;}
        .dq-pager{margin-top:16px;}
    </style>
@endsection
