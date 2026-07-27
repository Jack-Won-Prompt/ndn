@extends('portal.layout', ['active' => 'settlements'])
@section('title', '정착 처리 상세')

@php
    use App\Domains\Settlement\Enums\SettlementStatus;
@endphp

@section('body')
    <a class="ps-back" href="{{ route('portal.settlements.index') }}">← 목록</a>

    <div class="ps-grid">
        <section class="ps-panel">
            <div class="ps-panel__title">배정 건 #{{ $s->id }}</div>
            <dl class="ps-dl">
                <dt>근로자</dt><dd>{{ $s->worker?->name ?? '—' }}</dd>
                <dt>국적</dt><dd>{{ $s->worker?->nationality ?? '—' }}</dd>
                <dt>서비스 유형</dt><dd>{{ $s->type->label() }}</dd>
                <dt>현재 상태</dt><dd><span class="ps-badge ps-badge--{{ $s->status->value }}">{{ $s->status->label() }}</span></dd>
                <dt>배정 일시</dt><dd>{{ $s->assigned_at ? \App\Shared\Support\LocalTime::format($s->assigned_at) : '—' }}</dd>
                <dt>SLA 기한</dt><dd class="{{ $s->isOverdue() ? 'ps-over' : '' }}">{{ $s->sla_due_at ? \App\Shared\Support\LocalTime::format($s->sla_due_at) : '—' }}{{ $s->isOverdue() ? ' · 지연' : '' }}</dd>
                @if ($s->completed_at)
                    <dt>완료 일시</dt><dd>{{ \App\Shared\Support\LocalTime::format($s->completed_at) }}</dd>
                @endif
                @if ($s->partner_note)
                    <dt>처리 메모</dt><dd>{{ $s->partner_note }}</dd>
                @endif
            </dl>
        </section>

        <section class="ps-panel">
            <div class="ps-panel__title">상태 처리</div>
            @if ($s->status === SettlementStatus::Done)
                <p class="ps-done">완료된 건입니다.</p>
            @else
                @php
                    $next = $s->status === SettlementStatus::Processing ? SettlementStatus::Done : SettlementStatus::Processing;
                    $btnLabel = $next === SettlementStatus::Done ? '완료 처리' : '처리 시작';
                @endphp
                <form method="POST" action="{{ route('portal.settlements.process', $s->id) }}">
                    @csrf
                    <input type="hidden" name="target" value="{{ $next->value }}">
                    <label class="ps-label">처리 메모 (선택)</label>
                    <textarea name="note" class="ps-textarea" rows="3" maxlength="2000" placeholder="처리 내용·특이사항">{{ old('note') }}</textarea>
                    <button type="submit" class="ps-btn ps-btn--primary">{{ $btnLabel }} ({{ $next->label() }})</button>
                </form>
            @endif
        </section>
    </div>

    <section class="ps-panel">
        <div class="ps-panel__title">처리 증빙 문서 <span class="ps-hint">· 다운로드 시 대리점명 워터마크 삽입</span></div>

        @if ($s->documents->isEmpty())
            <p class="ps-empty2">업로드된 증빙이 없습니다.</p>
        @else
            <ul class="ps-docs">
                @foreach ($s->documents as $doc)
                    <li>
                        <span class="ps-doc__icon">{{ $doc->isImage() ? '🖼️' : '📄' }}</span>
                        <span class="ps-doc__name">{{ $doc->original_name }}</span>
                        <a class="ps-link" href="{{ route('portal.settlements.documents.show', [$s->id, $doc->id]) }}">다운로드</a>
                    </li>
                @endforeach
            </ul>
        @endif

        <form method="POST" action="{{ route('portal.settlements.documents.store', $s->id) }}" enctype="multipart/form-data" class="ps-upload">
            @csrf
            <input type="file" name="file" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf" required>
            <button type="submit" class="ps-btn">업로드</button>
        </form>
        @error('file')<div class="ps-fielderr">{{ $message }}</div>@enderror
    </section>

    @push('head')
    <style>
        .ps-back{display:inline-block;margin-bottom:14px;color:#6B7280;text-decoration:none;font-weight:600;font-size:14px;}
        .ps-back:hover{color:#1B1E24;}
        .ps-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;}
        @media (max-width:760px){.ps-grid{grid-template-columns:1fr;}}
        .ps-panel{background:#fff;border:1px solid #E3E6EA;border-radius:12px;padding:18px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
        .ps-panel__title{font-size:15px;font-weight:800;margin-bottom:12px;}
        .ps-hint{font-size:12px;font-weight:500;color:#9AA1AC;}
        .ps-dl{display:grid;grid-template-columns:96px 1fr;gap:8px 12px;margin:0;font-size:14px;}
        .ps-dl dt{color:#6B7280;font-weight:600;}
        .ps-dl dd{margin:0;color:#1B1E24;}
        .ps-over{color:#B42318;font-weight:700;}
        .ps-label{display:block;font-size:13px;color:#6B7280;font-weight:600;margin-bottom:6px;}
        .ps-textarea{width:100%;box-sizing:border-box;border:1px solid #D4DCDB;border-radius:9px;padding:9px 11px;font-family:inherit;font-size:14px;resize:vertical;margin-bottom:12px;}
        .ps-textarea:focus{outline:none;border-color:#1E9C92;box-shadow:0 0 0 3px rgba(30,156,146,.15);}
        .ps-btn{font-family:inherit;font-size:14px;font-weight:700;border:1px solid #D4DCDB;background:#fff;color:#1B1E24;border-radius:9px;padding:9px 16px;cursor:pointer;}
        .ps-btn:hover{border-color:#9AA1AC;}
        .ps-btn--primary{background:#1E9C92;color:#fff;border-color:#1E9C92;}
        .ps-btn--primary:hover{background:#178275;}
        .ps-done{color:#12695F;font-weight:700;margin:6px 0;}
        .ps-docs{list-style:none;padding:0;margin:0 0 14px;}
        .ps-docs li{display:flex;align-items:center;gap:10px;padding:9px 0;border-bottom:1px solid #F1F4F6;font-size:14px;}
        .ps-doc__name{flex:1;word-break:break-all;}
        .ps-empty2{color:#9AA1AC;font-size:14px;margin:4px 0 14px;}
        .ps-upload{display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding-top:8px;border-top:1px solid #F1F4F6;}
        .ps-fielderr{color:#B42318;font-size:13px;margin-top:8px;}
        .ps-badge{display:inline-block;padding:2px 9px;border-radius:100px;font-size:12px;font-weight:700;}
        .ps-badge--assigned{background:#E7F0FF;color:#1D4ED8;}
        .ps-badge--processing{background:#FFF3E0;color:#B45309;}
        .ps-badge--done{background:#E7F3F1;color:#12695F;}
        .ps-link{color:#1E9C92;font-weight:700;text-decoration:none;}
    </style>
    @endpush
@endsection
