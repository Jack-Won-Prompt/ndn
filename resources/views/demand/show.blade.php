<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>수요 신청 상세 — NDN</title>
</head>
<body>
    <h1>수요 신청 #{{ $demand->id }}</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <dl>
        <dt>농가</dt><dd>{{ $demand->farm?->name }}</dd>
        <dt>국적</dt><dd>{{ $demand->nationality }}</dd>
        <dt>인원</dt><dd>{{ $demand->headcount }}명</dd>
        <dt>연령대</dt><dd>{{ $demand->age_min }} – {{ $demand->age_max }}</dd>
        <dt>성별</dt><dd>{{ $demand->gender->label() }}</dd>
        <dt>형제 동반</dt><dd>{{ $demand->allow_siblings ? '허용' : '불허' }}</dd>
        <dt>품목</dt><dd>{{ $demand->crop }}</dd>
        <dt>기간</dt><dd>{{ $demand->period_start?->format('Y-m-d') }} ~ {{ $demand->period_end?->format('Y-m-d') }}</dd>
        <dt>상태</dt><dd>{{ $demand->status->label() }}</dd>
    </dl>

    @can('submit', $demand)
        <form method="POST" action="{{ route('demand.submit', $demand) }}">
            @csrf
            <button type="submit">제출하기</button>
        </form>
    @endcan

    <p><a href="{{ route('demand.index') }}">목록으로</a></p>
</body>
</html>
