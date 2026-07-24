@php /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $rows */ @endphp
@if ($rows->hasPages())
    <div class="mv2-card__foot">
        <div class="mv2-paging__info">전체 {{ number_format($rows->total()) }}건 · {{ $rows->currentPage() }}/{{ $rows->lastPage() }} 페이지</div>
        <div class="mv2-paging">
            @if ($rows->onFirstPage())
                <span class="is-disabled">이전</span>
            @else
                <a href="{{ $rows->previousPageUrl() }}">이전</a>
            @endif

            @foreach ($rows->getUrlRange(max(1, $rows->currentPage() - 2), min($rows->lastPage(), $rows->currentPage() + 2)) as $page => $url)
                @if ($page == $rows->currentPage())
                    <span class="is-current">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach

            @if ($rows->hasMorePages())
                <a href="{{ $rows->nextPageUrl() }}">다음</a>
            @else
                <span class="is-disabled">다음</span>
            @endif
        </div>
    </div>
@endif
