@extends('admin.screens.layout')
@section('title', '근로자')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">근로자</h1>
            <p class="screen__sub">등록된 근로자 목록 · 민감정보는 상세에서 감사 기록 후 제한 표시</p>
        </div>
        <form class="toolbar" method="GET" action="{{ url('admin/screen/workers') }}">
            <input type="text" name="q" value="{{ $q }}" placeholder="이름 검색">
            <button type="submit">검색</button>
        </form>
    </div>

    <div class="mv2-card">
        <div class="mv2-card__head">
            <span class="mv2-card__title"><span class="mv2-card__title-bar"></span>근로자 목록</span>
            <span class="mv2-paging__info">{{ number_format($rows->total()) }}건</span>
        </div>
        <div class="mv2-card__body--none">
            <div class="mv2-grid-wrap">
                <table class="mv2-table is-striped">
                    <thead>
                        <tr>
                            <th style="width:70px">번호</th>
                            <th>이름</th>
                            <th style="width:90px">국적</th>
                            <th style="width:90px">언어</th>
                            <th style="width:110px">상태</th>
                            <th style="width:130px">등록일</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $w)
                            <tr>
                                <td class="muted">{{ $w->id }}</td>
                                <td>
                                    <a class="link" href="javascript:void(0)"
                                       onclick="ndnOpenTab('workers/{{ $w->id }}', '근로자 #{{ $w->id }}')">{{ $w->name }}</a>
                                </td>
                                <td><span class="mv2-pill">{{ $w->nationality }}</span></td>
                                <td class="muted">{{ $w->locale }}</td>
                                <td><span class="mv2-pill {{ $w->status === 'active' ? 'mv2-pill--ok' : '' }}">{{ $w->status }}</span></td>
                                <td class="muted">{{ $w->created_at?->format('Y-m-d') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="mv2-table-empty">등록된 근로자가 없습니다.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @include('admin.screens._pager')
    </div>
@endsection
