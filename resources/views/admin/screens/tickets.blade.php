@extends('admin.screens.layout')
@section('title', '민원')

@php
    $pill = fn (string $s) => match ($s) {
        'open'        => 'mv2-pill--warn',
        'in_progress' => 'mv2-pill--info',
        'resolved'    => 'mv2-pill--ok',
        default       => '',
    };
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">민원</h1>
            <p class="screen__sub">근로자 발신 민원 (문제신고/문의/연장/조기귀국)</p>
        </div>
    </div>

    <div class="mv2-card">
        <div class="mv2-card__head">
            <span class="mv2-card__title"><span class="mv2-card__title-bar"></span>민원 목록</span>
            <span class="mv2-paging__info">{{ number_format($rows->total()) }}건</span>
        </div>
        <div class="mv2-card__body--none">
            <div class="mv2-grid-wrap">
                <table class="mv2-table is-striped">
                    <thead>
                        <tr>
                            <th style="width:60px">번호</th>
                            <th>근로자</th>
                            <th style="width:110px">유형</th>
                            <th>제목</th>
                            <th style="width:110px">상태</th>
                            <th style="width:140px">접수일시</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $t)
                            <tr>
                                <td class="muted">{{ $t->id }}</td>
                                <td>{{ $t->worker?->name ?? '—' }}</td>
                                <td><span class="mv2-pill">{{ $t->type->label() }}</span></td>
                                <td>{{ $t->subject }}</td>
                                <td><span class="mv2-pill {{ $pill($t->status->value) }}">{{ $t->status->label() }}</span></td>
                                <td class="muted">{{ $t->created_at?->format('Y-m-d H:i') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="mv2-table-empty">민원이 없습니다.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @include('admin.screens._pager')
    </div>
@endsection
