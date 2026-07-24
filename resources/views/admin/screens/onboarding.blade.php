@extends('admin.screens.layout')
@section('title', '온보딩 검수')

@php
    $badge = fn (string $status) => match ($status) {
        'draft'        => 'badge--gray',
        'submitted'    => 'badge--amber',
        'under_review' => 'badge--blue',
        'approved'     => 'badge--green',
        'rejected'     => 'badge--red',
        default        => 'badge--gray',
    };
@endphp

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">온보딩 검수</h1>
            <p class="screen__sub">셀프 온보딩 제출물 (본인 기입 정보는 암호화 저장)</p>
        </div>
    </div>

    <div class="table_type01_wrap">
        <div class="table_type01">
            <table>
                <thead>
                    <tr>
                        <th style="width:70px"><em>번호</em></th>
                        <th class="cell-left"><em>근로자</em></th>
                        <th style="width:130px"><em>상태</em></th>
                        <th style="width:160px"><em>제출일시</em></th>
                        <th class="cell-left"><em>검수 메모</em></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $o)
                        <tr>
                            <td class="muted">{{ $o->id }}</td>
                            <td class="cell-left">{{ $o->worker?->name ?? '—' }}</td>
                            <td><span class="badge {{ $badge($o->status->value) }}">{{ $o->status->label() }}</span></td>
                            <td class="muted">{{ $o->submitted_at?->format('Y-m-d H:i') ?? '—' }}</td>
                            <td class="cell-left muted">{{ $o->review_note ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-row">온보딩 제출물이 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.screens._pager')
@endsection
