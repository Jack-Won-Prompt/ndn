@extends('demand.layout')
@section('title', '수요 신청 상세')

@section('content')
    <div class="dp-head">
        <h1>수요 신청 #{{ $demand->id }}</h1>
        <a href="{{ route('demand.index') }}" class="dp-btn dp-btn--ghost">목록</a>
    </div>

    <div class="dp-card" style="padding:24px">
        <div class="dq-status">
            <span class="dq-badge dq-badge--{{ $demand->status->value }}">{{ $demand->status->label() }}</span>
            @if ($demand->submitted_at)
                <span class="dq-sub">제출: {{ \App\Shared\Support\LocalTime::format($demand->submitted_at) }}</span>
            @endif
        </div>

        <dl class="dq-dl">
            <div><dt>농가</dt><dd>{{ $demand->farm?->name ?? '—' }}</dd></div>
            <div><dt>관할 시·군</dt><dd>{{ $demand->city?->name ?? '—' }}</dd></div>
            <div><dt>국적</dt><dd>{{ $demand->nationality }}</dd></div>
            <div><dt>인원</dt><dd>{{ $demand->headcount }}명</dd></div>
            <div><dt>연령대</dt><dd>{{ $demand->age_min ?? '무관' }} ~ {{ $demand->age_max ?? '무관' }}</dd></div>
            <div><dt>성별</dt><dd>{{ $demand->gender->label() }}</dd></div>
            <div><dt>형제·가족 동반</dt><dd>{{ $demand->allow_siblings ? '허용' : '불허' }}</dd></div>
            <div><dt>품목</dt><dd>{{ $demand->crop }}</dd></div>
            <div><dt>근무 기간</dt><dd>{{ $demand->period_start?->format('Y-m-d') }} ~ {{ $demand->period_end?->format('Y-m-d') }}</dd></div>
            <div class="dq-dl__full"><dt>메모</dt><dd>{{ $demand->note ?: '—' }}</dd></div>
        </dl>

        @can('submit', $demand)
            <form method="POST" action="{{ route('demand.submit', $demand) }}" class="dq-submit"
                  onsubmit="return confirm('제출하면 수정할 수 없습니다. 제출할까요?');">
                @csrf
                <p class="dq-hint">아직 <b>작성 중</b> 상태입니다. 제출하면 시청 취합 대상이 되며 이후 수정할 수 없습니다.</p>
                <button type="submit" class="dp-btn">제출하기</button>
            </form>
        @endcan
    </div>

    <style>
        .dq-status{display:flex;align-items:center;gap:12px;margin-bottom:18px;}
        .dq-sub{font-size:13px;color:#6B7280;}
        .dq-badge{font-size:12px;font-weight:700;border-radius:100px;padding:3px 11px;}
        .dq-badge--draft{background:#F0F2F4;color:#6B7280;}
        .dq-badge--submitted{background:#E9F6F4;color:#178578;}
        .dq-badge--aggregated{background:#E6F0FB;color:#1D65B8;}
        .dq-badge--letter_issued{background:#E7F6EC;color:#1B7F43;}
        .dq-badge--rejected{background:#FDECEC;color:#B42318;}
        .dq-dl{display:grid;grid-template-columns:1fr 1fr;gap:0;margin:0;border-top:1px solid #EEF1F4;}
        .dq-dl>div{display:flex;gap:12px;padding:12px 4px;border-bottom:1px solid #EEF1F4;}
        .dq-dl__full{grid-column:1 / -1;}
        .dq-dl dt{width:120px;flex:0 0 120px;color:#6B7280;font-size:13px;font-weight:700;margin:0;}
        .dq-dl dd{margin:0;font-size:14px;color:#1B1E24;}
        .dq-submit{margin-top:22px;display:flex;flex-direction:column;align-items:flex-end;gap:4px;}
        .dq-hint{font-size:12px;color:#6B7280;margin:0 0 6px;}
        @media (max-width:640px){.dq-dl{grid-template-columns:1fr;}}
    </style>
@endsection
