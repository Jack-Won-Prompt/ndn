<?php

declare(strict_types=1);

use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Reporting\Actions\GenerateMonthlyReportAction;
use App\Domains\Support\Models\SupportTicket;

/**
 * 지자체 월간 보고 (업무흐름 §10).
 *
 * 점검 집계의 근거는 근무상태 종합 점검표다. 월별 인터뷰 6항목은 폐기됐다.
 */
it('월간 집계 데이터를 산출한다', function () {
    WorkReview::factory()->create(['reviewed_at' => '2026-07-05 10:00']);
    WorkReview::factory()->create([
        'reviewed_at' => '2026-07-06 10:00',
        'risk_level' => RiskLevel::High->value,
        'risk_score' => 12,
    ]);
    // 다른 달 건은 집계에 들어가면 안 된다.
    WorkReview::factory()->create(['reviewed_at' => '2026-08-01 10:00']);
    SupportTicket::factory()->create(['created_at' => '2026-07-10']);

    $data = app(GenerateMonthlyReportAction::class)->data(2026, 7);

    expect($data['interview_total'])->toBe(2)
        ->and($data['risk_high'])->toBe(1)
        ->and($data['tickets_total'])->toBe(1);
});

it('월간 보고서 PDF 를 생성한다 (%PDF 헤더)', function () {
    $pdf = app(GenerateMonthlyReportAction::class)->pdf(2026, 7);
    $output = $pdf->output();

    expect(substr($output, 0, 4))->toBe('%PDF');
});
