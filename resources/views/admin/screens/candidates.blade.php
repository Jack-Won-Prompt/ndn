@extends('admin.screens.layout')
@section('title', '후보자·평가')

@php
    $pill = fn (string $s) => match ($s) {
        'passed'   => 'mv2-pill--ok',
        'held'     => 'mv2-pill--warn',
        'rejected' => 'mv2-pill--err',
        default    => '',
    };
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">후보자·평가</h1>
            <p class="screen__sub">모집 명단 및 면접 결과 (합격/보류/불합격 · 보류자 대기열 순번)</p>
        </div>
    </div>

    <div class="mv2-card">
        <div class="mv2-card__head">
            <span class="mv2-card__title"><span class="mv2-card__title-bar"></span>후보자 목록</span>
            <span class="mv2-paging__info">{{ number_format($rows->total()) }}건</span>
        </div>
        <div class="mv2-card__body--none">
            <div class="mv2-grid-wrap">
                <table class="mv2-table is-striped">
                    <thead>
                        <tr>
                            <th style="width:70px">번호</th>
                            <th>이름</th>
                            <th style="width:80px">국적</th>
                            <th style="width:70px">나이</th>
                            <th style="width:110px">상태</th>
                            <th style="width:110px">대기 순번</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $c)
                            <tr>
                                <td class="muted">{{ $c->id }}</td>
                                <td>{{ $c->name }}</td>
                                <td><span class="mv2-pill">{{ $c->nationality }}</span></td>
                                <td class="muted">{{ $c->age ?? '—' }}</td>
                                <td><span class="mv2-pill {{ $pill($c->status->value) }}">{{ $c->status->label() }}</span></td>
                                <td class="muted">{{ $c->queue_position ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="mv2-table-empty">후보자가 없습니다.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @include('admin.screens._pager')
    </div>
@endsection
