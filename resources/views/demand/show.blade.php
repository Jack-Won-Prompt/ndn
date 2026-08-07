@extends('portal.layout', ['active' => 'demand'])
@section('title', '수요 신청 상세')

@section('body')
    <div class="nd-pagehead nd-pagehead--row">
        <div>
            <h1>수요 신청 #{{ $demand->id }}</h1>
            <p>
                <span class="nd-badge nd-badge--{{ $demand->status->value === 'rejected' ? 'err' : ($demand->status->value === 'draft' ? 'mute' : 'ok') }}">
                    {{ $demand->status->label() }}
                </span>
                @if ($demand->submitted_at)
                    <span style="margin-left:8px">제출 {{ \App\Shared\Support\LocalTime::format($demand->submitted_at) }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('demand.index') }}" class="nd-btn nd-btn--line nd-btn--sm">목록</a>
    </div>

    <div class="nd-panel">
        <dl class="nd-dl">
            <div><dt>농가</dt><dd>{{ $demand->farm?->name ?? '—' }}</dd></div>
            <div><dt>관할 시·군</dt><dd>{{ $demand->city?->name ?? '—' }}</dd></div>
            <div><dt>국적</dt><dd>{{ $demand->nationality }}</dd></div>
            <div><dt>인원</dt><dd>{{ $demand->headcount }}명</dd></div>
            <div><dt>연령대</dt><dd>{{ $demand->age_min ?? '무관' }} ~ {{ $demand->age_max ?? '무관' }}</dd></div>
            <div><dt>성별</dt><dd>{{ $demand->gender->label() }}</dd></div>
            <div><dt>형제·가족 동반</dt><dd>{{ $demand->allow_siblings ? '허용' : '불허' }}</dd></div>
            <div><dt>품목</dt><dd>{{ $demand->crop }}</dd></div>
            <div><dt>근무 기간</dt><dd>{{ $demand->period_start?->format('Y-m-d') }} ~ {{ $demand->period_end?->format('Y-m-d') }}</dd></div>
            <div class="nd-dl__full"><dt>메모</dt><dd>{{ $demand->note ?: '—' }}</dd></div>
        </dl>

        @can('submit', $demand)
            <form method="POST" action="{{ route('demand.submit', $demand) }}"
                  onsubmit="return confirm('제출하면 수정할 수 없습니다. 제출할까요?');">
                @csrf
                <p class="nd-formhint">
                    아직 <b>작성 중</b>입니다. 제출하면 시청 취합 대상이 되며 이후 수정할 수 없습니다.
                </p>
                <div class="nd-formfoot" style="margin-top:10px">
                    <button type="submit" class="nd-btn nd-btn--ink nd-btn--sm">제출하기</button>
                </div>
            </form>
        @endcan
    </div>
@endsection
