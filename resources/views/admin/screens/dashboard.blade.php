@extends('admin.screens.layout')
@section('title', '대시보드')

@section('content')
    <div class="screen__head">
        <div>
            <h1 class="screen__title">대시보드</h1>
            <p class="screen__sub">N.D.N Korea 계절근로자 통합관리 현황</p>
        </div>
        <a class="report-btn" href="{{ route('admin.reports.monthly', ['year' => now()->year, 'month' => now()->month]) }}" target="_blank" rel="noopener">
            이번 달 지자체 보고서 PDF
        </a>
    </div>
    <style>
        .report-btn { padding: 9px 16px; font-size: 13px; font-weight: 600; color: #fff;
            background: var(--mv2-primary-500); border-radius: var(--mv2-r-sm); text-decoration: none; }
        .report-btn:hover { background: var(--mv2-primary-600); }
    </style>

    <div class="stats">
        <div class="stat">
            <div class="stat__label">등록 근로자</div>
            <div class="stat__value">{{ number_format($stats['workers']) }}</div>
            <div class="stat__hint">전체 근로자 수</div>
        </div>
        <div class="stat">
            <div class="stat__label">처리 중 수요</div>
            <div class="stat__value">{{ number_format($stats['demand']) }}</div>
            <div class="stat__hint">제출·취합 단계</div>
        </div>
        <div class="stat {{ $stats['onboarding'] > 0 ? 'stat--alert' : '' }}">
            <div class="stat__label">온보딩 검수 대기</div>
            <div class="stat__value">{{ number_format($stats['onboarding']) }}</div>
            <div class="stat__hint">제출됨, 검수 필요</div>
        </div>
        <div class="stat {{ $stats['sos'] > 0 ? 'stat--alert' : 'stat--ok' }}">
            <div class="stat__label">미처리 SOS</div>
            <div class="stat__value">{{ number_format($stats['sos']) }}</div>
            <div class="stat__hint">{{ $stats['sos'] > 0 ? '즉시 확인 필요' : '없음' }}</div>
        </div>
    </div>

    <div class="notice" style="margin-top:22px">
        ⚠ 통계·연혁·협력기관 등 일부 값은 자리표시자이며 확정 전까지 대외 공개 금지.
    </div>
@endsection
