@extends('admin.screens.layout')
@section('title', '수요 신청')

@php
    $pill = fn (string $status) => match ($status) {
        'submitted'     => 'mv2-pill--warn',
        'aggregated'    => 'mv2-pill--info',
        'letter_issued' => 'mv2-pill--ok',
        'rejected'      => 'mv2-pill--err',
        default         => '',
    };
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">수요 신청</h1>
            <p class="screen__sub">농가 수요 신청 현황 (모니터링 · 상태 전이는 도메인 Action)</p>
        </div>
    </div>

    <div class="mv2-card">
        <div class="mv2-card__head">
            <span class="mv2-card__title"><span class="mv2-card__title-bar"></span>수요 신청 목록</span>
            <span class="mv2-paging__info">{{ number_format($rows->total()) }}건</span>
        </div>
        <div class="mv2-card__body--none">
            <div class="mv2-grid-wrap">
                <table class="mv2-table is-striped">
                    <thead>
                        <tr>
                            <th style="width:70px">번호</th>
                            <th>농가</th>
                            <th style="width:80px">국적</th>
                            <th style="width:80px">인원</th>
                            <th>품목</th>
                            <th style="width:120px">상태</th>
                            <th style="width:120px">시작일</th>
                            <th style="width:120px">등록일</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $d)
                            <tr>
                                <td class="muted">{{ $d->id }}</td>
                                <td>{{ $d->farm?->name ?? '—' }}</td>
                                <td><span class="mv2-pill">{{ $d->nationality }}</span></td>
                                <td class="num">{{ $d->headcount }}명</td>
                                <td class="muted">{{ $d->crop }}</td>
                                <td><span class="mv2-pill {{ $pill($d->status->value) }}">{{ $d->status->label() }}</span></td>
                                <td class="muted">{{ $d->period_start?->format('Y-m-d') }}</td>
                                <td class="muted">{{ $d->created_at?->format('Y-m-d') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="mv2-table-empty">등록된 수요 신청이 없습니다.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @include('admin.screens._pager')
    </div>
@endsection
