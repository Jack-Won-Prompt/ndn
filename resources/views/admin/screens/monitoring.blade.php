@extends('admin.screens.layout')
@section('title', '월별 점검')

@php
    $pill = fn (string $s) => match ($s) {
        'high'   => 'mv2-pill--err',
        'medium' => 'mv2-pill--warn',
        default  => 'mv2-pill--ok',
    };
    $ox = fn (bool $b) => $b
        ? '<span class="mv2-pill mv2-pill--ok">양호</span>'
        : '<span class="mv2-pill mv2-pill--err">이상</span>';
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">월별 점검</h1>
            <p class="screen__sub">월별 인터뷰 6개 항목 · 이탈 리스크(행동 신호 기반, 위치 추적 미사용)</p>
        </div>
    </div>

    <div class="mv2-card">
        <div class="mv2-card__head">
            <span class="mv2-card__title"><span class="mv2-card__title-bar"></span>인터뷰 기록</span>
            <span class="mv2-paging__info">{{ number_format($rows->total()) }}건</span>
        </div>
        <div class="mv2-card__body--none">
            <div class="mv2-grid-wrap">
                <table class="mv2-table is-striped">
                    <thead>
                        <tr>
                            <th style="width:60px">번호</th>
                            <th>근로자</th>
                            <th style="width:110px">점검일</th>
                            <th style="width:80px">급여</th>
                            <th style="width:80px">차별없음</th>
                            <th style="width:80px">규칙</th>
                            <th style="width:80px">단체생활</th>
                            <th style="width:80px">건강</th>
                            <th style="width:90px">이탈징후</th>
                            <th style="width:90px">리스크</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $iv)
                            <tr>
                                <td class="muted">{{ $iv->id }}</td>
                                <td>{{ $iv->worker?->name ?? '—' }}</td>
                                <td class="muted">{{ $iv->interviewed_on?->format('Y-m-d') }}</td>
                                <td>{!! $ox($iv->pay_received) !!}</td>
                                <td>{!! $ox($iv->no_discrimination) !!}</td>
                                <td>{!! $ox($iv->follows_rules) !!}</td>
                                <td>{!! $ox($iv->adapts_group) !!}</td>
                                <td>{!! $ox($iv->health_ok) !!}</td>
                                <td>{!! $ox($iv->no_flight_signs) !!}</td>
                                <td><span class="mv2-pill {{ $pill($iv->risk_level->value) }}">{{ $iv->risk_level->label() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="mv2-table-empty">점검 기록이 없습니다.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @include('admin.screens._pager')
    </div>
@endsection
