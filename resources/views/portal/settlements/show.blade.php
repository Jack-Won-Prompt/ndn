@extends('portal.layout', ['active' => 'settlements'])
@section('title', '정착 처리 상세')

@php
    use App\Domains\Settlement\Enums\SettlementStatus;
    use App\Shared\Support\LocalTime;
@endphp

@section('body')
    <div class="nd-pagehead nd-pagehead--row">
        <div>
            <h1>배정 건 #{{ $s->id }}</h1>
            <p>
                <span class="nd-badge nd-badge--{{ $s->status->value === 'done' ? 'ok' : ($s->status->value === 'processing' ? 'warn' : 'mute') }}">
                    {{ $s->status->label() }}
                </span>
                <span style="margin-left:8px">{{ $s->type->label() }}</span>
            </p>
        </div>
        <a href="{{ route('portal.settlements.index') }}" class="nd-btn nd-btn--line nd-btn--sm">목록</a>
    </div>

    <div class="ps-grid">
        <section class="nd-panel">
            <h2 class="nd-h4">배정 내용</h2>
            <dl class="nd-dl nd-dl--one" style="border-top:0">
                <div><dt>근로자</dt><dd>{{ $s->worker?->name ?? '—' }}</dd></div>
                <div><dt>국적</dt><dd>{{ $s->worker?->nationality ?? '—' }}</dd></div>
                <div><dt>서비스 유형</dt><dd>{{ $s->type->label() }}</dd></div>
                <div><dt>배정 일시</dt><dd>{{ $s->assigned_at ? LocalTime::format($s->assigned_at) : '—' }}</dd></div>
                <div>
                    <dt>SLA 기한</dt>
                    <dd class="{{ $s->isOverdue() ? 'nd-over' : '' }}">
                        {{ $s->sla_due_at ? LocalTime::format($s->sla_due_at) : '—' }}{{ $s->isOverdue() ? ' · 지연' : '' }}
                    </dd>
                </div>
                @if ($s->completed_at)
                    <div><dt>완료 일시</dt><dd>{{ LocalTime::format($s->completed_at) }}</dd></div>
                @endif
                @if ($s->partner_note)
                    <div><dt>처리 메모</dt><dd>{{ $s->partner_note }}</dd></div>
                @endif
            </dl>
        </section>

        <section class="nd-panel">
            <h2 class="nd-h4">상태 처리</h2>

            @if ($s->status === SettlementStatus::Done)
                <p class="ps-done">완료된 건입니다.</p>
            @else
                @php
                    $next = $s->status === SettlementStatus::Processing
                        ? SettlementStatus::Done
                        : SettlementStatus::Processing;
                    $btnLabel = $next === SettlementStatus::Done ? '완료 처리' : '처리 시작';
                @endphp
                <form method="POST" action="{{ route('portal.settlements.process', $s->id) }}">
                    @csrf
                    <input type="hidden" name="target" value="{{ $next->value }}">
                    <div class="nd-field">
                        <label for="ps-note">처리 메모 (선택)</label>
                        <textarea class="nd-textarea" id="ps-note" name="note" rows="3" maxlength="2000"
                                  placeholder="처리 내용·특이사항">{{ old('note') }}</textarea>
                    </div>
                    <button type="submit" class="nd-btn nd-btn--ink nd-btn--sm">{{ $btnLabel }}</button>
                </form>
            @endif
        </section>
    </div>

    <section class="nd-panel" style="margin-top:16px">
        <h2 class="nd-h4">
            처리 증빙 문서
            <span class="ps-hint">· 내려받으면 대리점명 워터마크가 들어갑니다</span>
        </h2>

        @if ($s->documents->isEmpty())
            <p class="ps-none">업로드된 증빙이 없습니다.</p>
        @else
            <ul class="ps-docs">
                @foreach ($s->documents as $doc)
                    <li>
                        <span class="ps-docs__icon" aria-hidden="true">{{ $doc->isImage() ? '🖼️' : '📄' }}</span>
                        <span class="ps-docs__name">{{ $doc->original_name }}</span>
                        <a class="nd-btn nd-btn--line nd-btn--sm"
                           href="{{ route('portal.settlements.documents.show', [$s->id, $doc->id]) }}">내려받기</a>
                    </li>
                @endforeach
            </ul>
        @endif

        <form class="ps-upload" method="POST" enctype="multipart/form-data"
              action="{{ route('portal.settlements.documents.store', $s->id) }}">
            @csrf
            <input type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required>
            <button type="submit" class="nd-btn nd-btn--line nd-btn--sm">업로드</button>
        </form>
        @error('file')<div class="ps-err">{{ $message }}</div>@enderror
    </section>

    @push('head')
    <style>
        .ps-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .ps-grid .nd-h4 { margin: 0 0 14px; }
        .ps-hint { font-size: 13px; font-weight: 500; color: var(--nd-text-3); }
        .ps-done { color: var(--nd-ok); font-weight: 700; margin: 4px 0 0; }
        .ps-none { color: var(--nd-text-3); font-size: 15px; margin: 4px 0 16px; }
        .ps-docs { list-style: none; padding: 0; margin: 0 0 16px; }
        .ps-docs li { display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid var(--nd-line); font-size: 15px; }
        .ps-docs__name { flex: 1; word-break: break-all; }
        .ps-upload { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; padding-top: 14px; border-top: 1px solid var(--nd-line); }
        .ps-err { color: var(--nd-err); font-size: 14px; margin-top: 10px; }
        @media (max-width: 760px) { .ps-grid { grid-template-columns: 1fr; } }
    </style>
    @endpush
@endsection
