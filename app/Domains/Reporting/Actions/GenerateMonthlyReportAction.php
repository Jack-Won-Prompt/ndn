<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Actions;

use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\SupportTicket;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Carbon\CarbonImmutable;

/**
 * 지자체 제출용 월간 보고서 생성 (업무흐름 §10-2, CLAUDE.md §12: Reporting).
 *
 * 해당 월의 점검·이탈위험·민원 집계를 PDF 로 출력한다. 개인정보는 담지 않고
 * 집계 수치만 포함한다.
 */
class GenerateMonthlyReportAction
{
    /**
     * @return array<string, mixed> 집계 데이터 (테스트·미리보기에서 재사용)
     */
    public function data(int $year, int $month): array
    {
        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        $interviews = MonthlyInterview::whereBetween('interviewed_on', [$start->toDateString(), $end->toDateString()]);

        return [
            'year' => $year,
            'month' => $month,
            'generated_at' => now()->format('Y-m-d H:i'),
            'active_workers' => Worker::where('status', 'active')->count(),
            'interview_total' => (clone $interviews)->count(),
            'risk_high' => (clone $interviews)->where('risk_level', RiskLevel::High->value)->count(),
            'risk_medium' => (clone $interviews)->where('risk_level', RiskLevel::Medium->value)->count(),
            'tickets_open' => SupportTicket::where('status', 'open')
                ->whereBetween('created_at', [$start, $end])->count(),
            'tickets_total' => SupportTicket::whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    public function pdf(int $year, int $month): PdfInstance
    {
        return Pdf::loadView('reports.monthly', ['r' => $this->data($year, $month)])
            ->setPaper('a4');
    }
}
