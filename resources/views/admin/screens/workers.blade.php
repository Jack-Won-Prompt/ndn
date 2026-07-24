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

    <div class="table_type01_wrap">
        <div class="table_type01">
            <table>
                <thead>
                    <tr>
                        <th style="width:70px"><em>번호</em></th>
                        <th class="cell-left"><em>이름</em></th>
                        <th style="width:90px"><em>국적</em></th>
                        <th style="width:90px"><em>언어</em></th>
                        <th style="width:110px"><em>상태</em></th>
                        <th style="width:130px"><em>등록일</em></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $w)
                        <tr>
                            <td class="muted">{{ $w->id }}</td>
                            <td class="cell-left">
                                <a class="rowlink" href="javascript:void(0)"
                                   onclick="ndnOpenTab('workers/{{ $w->id }}', '근로자 #{{ $w->id }}')">{{ $w->name }}</a>
                            </td>
                            <td><span class="badge badge--gray">{{ $w->nationality }}</span></td>
                            <td class="muted">{{ $w->locale }}</td>
                            <td>
                                <span class="badge {{ $w->status === 'active' ? 'badge--green' : 'badge--gray' }}">{{ $w->status }}</span>
                            </td>
                            <td class="muted">{{ $w->created_at?->format('Y-m-d') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="empty-row">등록된 근로자가 없습니다.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('admin.screens._pager')
@endsection
