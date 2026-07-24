<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>수요 신청 목록 — NDN</title>
</head>
<body>
    <h1>수요 신청 목록</h1>

    @if (session('status'))
        <p role="status">{{ session('status') }}</p>
    @endif

    <table>
        <thead>
            <tr>
                <th>농가</th><th>국적</th><th>인원</th><th>품목</th><th>상태</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($demands as $demand)
                <tr>
                    <td><a href="{{ route('demand.show', $demand) }}">{{ $demand->farm?->name }}</a></td>
                    <td>{{ $demand->nationality }}</td>
                    <td>{{ $demand->headcount }}</td>
                    <td>{{ $demand->crop }}</td>
                    <td>{{ $demand->status->label() }}</td>
                </tr>
            @empty
                <tr><td colspan="5">등록된 수요 신청이 없습니다.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{ $demands->links() }}
</body>
</html>
