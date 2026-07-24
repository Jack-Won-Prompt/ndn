@extends('admin.screens.layout')
@section('title', '온보딩 검수')

@php
    $pill = fn (string $status) => match ($status) {
        'submitted'    => 'mv2-pill--warn',
        'under_review' => 'mv2-pill--info',
        'approved'     => 'mv2-pill--ok',
        'rejected'     => 'mv2-pill--err',
        default        => '',
    };
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">온보딩 검수</h1>
            <p class="screen__sub">셀프 온보딩 제출물 (본인 기입 정보는 암호화 저장)</p>
        </div>
    </div>

    <div class="mv2-card">
        <div class="mv2-card__head">
            <span class="mv2-card__title"><span class="mv2-card__title-bar"></span>온보딩 제출물</span>
            <span class="mv2-paging__info">{{ number_format($rows->total()) }}건</span>
        </div>
        <div class="mv2-card__body--none">
            <div class="mv2-grid-wrap">
                <table class="mv2-table is-striped">
                    <thead>
                        <tr>
                            <th style="width:70px">번호</th>
                            <th>근로자</th>
                            <th style="width:130px">상태</th>
                            <th style="width:160px">제출일시</th>
                            <th>검수 메모</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $o)
                            <tr>
                                <td class="muted">{{ $o->id }}</td>
                                <td>{{ $o->worker?->name ?? '—' }}</td>
                                <td><span class="mv2-pill {{ $pill($o->status->value) }}">{{ $o->status->label() }}</span></td>
                                <td class="muted">{{ $o->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                                <td class="muted">{{ $o->review_note ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="mv2-table-empty">온보딩 제출물이 없습니다.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @include('admin.screens._pager')
    </div>
@endsection
