{{-- SR 목록 행 — 최초 렌더용. 등록·상태 변경 후에는 JS 가 같은 구조로 다시 그린다. --}}
@forelse ($rows as $r)
    <tr data-id="{{ $r['id'] }}">
        <td class="c">#{{ $r['id'] }}</td>
        <td>{{ $r['title'] }}</td>
        <td class="c">{{ $r['requester'] ?? '—' }}</td>
        <td class="c">{{ $r['assignee'] ?? '—' }}</td>
        <td class="c">{{ $r['replies'] }}</td>
        <td class="c">{{ $r['created'] }}</td>
        <td class="c"><span class="sr-badge sr-badge--{{ $r['status'] }}">{{ $r['status_label'] }}</span></td>
    </tr>
@empty
    <tr><td colspan="7" class="sr-empty">등록된 SR 이 없습니다.</td></tr>
@endforelse
