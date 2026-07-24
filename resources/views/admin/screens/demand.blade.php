@extends('admin.screens.layout')
@section('title', '수요 신청')

@php
    $badge = fn (string $status) => match ($status) {
        'draft'         => 'badge--gray',
        'submitted'     => 'badge--amber',
        'aggregated'    => 'badge--blue',
        'letter_issued' => 'badge--green',
        'rejected'      => 'badge--red',
        default         => 'badge--gray',
    };
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">수요 신청</h1>
            <p class="screen__sub">농가 수요 신청 현황 (모니터링 · 상태 전이는 도메인 Action)</p>
        </div>
    </div>

    <div class="table_type01_wrap">
        <div class="table_type01">
            <table>
                <thead>
                    <tr>
                        <th style="width:70px"><em>번호</em></th>
                        <th class="cell-left"><em>농가</em></th>
                        <th style="width:80px"><em>국적</em></th>
                        <th style="width:80px"><em>인원</em></th>
                        <th class="cell-left"><em>품목</em></th>
                        <th style="width:120px"><em>상태</em></th>
                        <th style="width:120px"><em>시작일</em></th>
                        <th style="width:120px"><em>등록일</em></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $d)
                        <tr>
                            <td class="muted">{{ $d->id }}</td>
                            <td class="cell-left">{{ $d->farm?->name ?? '—' }}</td>
                            <td><span class="badge badge--gray">{{ $d->nationality }}</span></td>
                            <td class="cell-num">{{ $d->headcount }}명</td>
                            <td class="cell-left muted">{{ $d->crop }}</td>
                            <td><span class="badge {{ $badge($d->status->value) }}">{{ $d->status->label() }}</span></td>
                            <td class="muted">{{ $d->period_start?->format('Y-m-d') }}</td>
                            <td class="muted">{{ $d->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-row">등록된 수요 신청이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.screens._pager')
@endsection
