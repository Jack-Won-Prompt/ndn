<?php

declare(strict_types=1);

use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Reporting\Actions\GenerateMonthlyReportAction;
use App\Domains\Support\Models\SupportTicket;

/**
 * 지자체 월간 보고 (업무흐름 §10).
 */
it('월간 집계 데이터를 산출한다', function () {
    MonthlyInterview::factory()->create(['interviewed_on' => '2026-07-05']);
    MonthlyInterview::factory()->highRisk()->create(['interviewed_on' => '2026-07-06']);
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
