@extends('portal.layout', ['active' => 'demand'])
@section('title', '수요 신청')

@section('body')
    <div class="nd-pagehead nd-pagehead--row">
        <div>
            <h1>수요 신청</h1>
            <p>농가가 필요한 인원을 신청하면 관할 시·군이 취합합니다.</p>
        </div>
        <a href="{{ route('demand.create') }}" class="nd-btn nd-btn--ink nd-btn--sm">+ 새 수요 신청</a>
    </div>

    <div class="nd-tablewrap nd-tablewrap--dense">
        <table class="nd-table">
            <thead>
                <tr>
                    <th style="width:64px">번호</th>
                    <th>농가</th>
                    <th style="width:88px">국적</th>
                    <th style="width:72px">인원</th>
                    <th>품목</th>
                    <th style="width:210px">기간</th>
                    <th style="width:112px">상태</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($demands as $demand)
                    <tr data-href="{{ route('demand.show', $demand) }}">
                        <td class="nd-c">{{ $demand->id }}</td>
                        <td>{{ $demand->farm?->name ?? '—' }}</td>
                        <td class="nd-c">{{ $demand->nationality }}</td>
                        <td class="nd-c">{{ $demand->headcount }}명</td>
                        <td>{{ $demand->crop }}</td>
                        <td class="nd-c">{{ $demand->period_start?->format('Y-m-d') }} ~ {{ $demand->period_end?->format('Y-m-d') }}</td>
                        <td class="nd-c">
                            <span class="nd-badge nd-badge--{{ $demand->status->value === 'rejected' ? 'err' : ($demand->status->value === 'draft' ? 'mute' : 'ok') }}">
                                {{ $demand->status->label() }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="nd-empty">등록된 수요 신청이 없습니다. [+ 새 수요 신청]으로 시작하세요.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:18px">{{ $demands->links() }}</div>

    @push('scripts')
    <script>
        // 행 전체를 눌러 상세로 간다. 칸마다 링크를 두면 눌러야 할 곳을 찾게 된다.
        document.querySelectorAll('tr[data-href]').forEach(function (tr) {
            tr.addEventListener('click', function () { location.href = tr.dataset.href; });
        });
    </script>
    @endpush
@endsection
