<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Actions;

use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\SupportTicket;
use App\Shared\Support\LocalTime;
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

        // 점검 집계의 근거는 근무상태 종합 점검표다. 월별 인터뷰 6항목은 폐기됐다.
        $reviews = WorkReview::whereBetween('reviewed_at', [$start->startOfDay(), $end->endOfDay()]);

        return [
            'year' => $year,
            'month' => $month,
            'generated_at' => LocalTime::format(now()),
            'active_workers' => Worker::where('status', WorkerStatus::Active->value)->count(),
            'interview_total' => (clone $reviews)->count(),
            'risk_high' => (clone $reviews)->where('risk_level', RiskLevel::High->value)->count(),
            'risk_medium' => (clone $reviews)->where('risk_level', RiskLevel::Medium->value)->count(),
            'tickets_open' => SupportTicket::where('status', 'open')
                ->whereBetween('created_at', [$start, $end])->count(),
            'tickets_total' => SupportTicket::whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    public function pdf(int $year, int $month): PdfInstance
    {
        // dompdf 폰트 메트릭 캐시 디렉터리 (쓰기 가능해야 함)
        $fontCache = storage_path('fonts');
        if (! is_dir($fontCache)) {
            mkdir($fontCache, 0775, true);
        }

        return Pdf::loadView('reports.monthly', ['r' => $this->data($year, $month)])
            ->setPaper('a4')
            ->setOptions([
                'isRemoteEnabled' => true,          // @font-face 로컬 TTF 로드 허용
                'isFontSubsettingEnabled' => true,  // 사용 글리프만 임베드(용량 절감)
                'chroot' => base_path(),            // resources/fonts 접근 허용
                'fontDir' => $fontCache,
                'fontCache' => $fontCache,
                'defaultFont' => 'NanumGothic',
            ]);
    }
}
