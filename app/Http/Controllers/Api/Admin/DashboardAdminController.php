<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Arrival\Models\ArrivalRecord;
use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Monitoring\Enums\RiskLevel;
use App\Domains\Monitoring\Models\FarmVisit;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Support\Enums\SosStatus;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SosAlert;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Api\Admin\Concerns\ScopesPortalQueries;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\Support\PortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 관리자 앱 대시보드 — 로그인 후 첫 화면.
 *
 * "지금 내가 할 일이 무엇인가" 를 한 화면에 모은다. 목록을 일일이 열어 보지 않아도
 * 밀린 건이 보여야 하기 때문이다.
 *
 * 모든 집계는 역할 스코프를 탄다(PortalScope). 시청·농가는 자기 범위의 숫자만 본다.
 * 개인정보를 내려주지 않고 **건수만** 집계하므로 §7-6 열람 로그 대상이 아니다.
 */
class DashboardAdminController extends Controller
{
    use ScopesPortalQueries;

    /** 입국 임박 기준(일) — 이 안에 도착하는 건을 '임박' 으로 본다. */
    private const ARRIVAL_SOON_DAYS = 7;

    public function __invoke(Request $request): JsonResponse
    {
        $actor = $this->actor($request);
        $canDecide = PortalScope::canDecide($actor);

        $openSos = PortalScope::byWorker(SosAlert::query(), $actor)
            ->where('status', SosStatus::Open->value);

        // 가장 오래 방치된 미확인 SOS — 몇 시간째인지가 긴급도의 핵심이다.
        $oldestSos = (clone $openSos)->orderBy('alerted_at')->first();

        return response()->json([
            'data' => [
                // ── 긴급 ────────────────────────────────────────────────
                'sos' => [
                    'open' => (clone $openSos)->count(),
                    'oldest_minutes' => $oldestSos?->responseMinutes(),
                ],

                // ── 내가 처리해야 할 것 ─────────────────────────────────
                'todo' => [
                    'worker_approval' => PortalScope::workers(Worker::query(), $actor)
                        ->where('status', WorkerStatus::Pending->value)->count(),

                    'onboarding_review' => PortalScope::byWorker(OnboardingSubmission::query(), $actor)
                        ->whereIn('status', [
                            OnboardingStatus::Submitted->value,
                            OnboardingStatus::UnderReview->value,
                        ])->count(),

                    'open_tickets' => PortalScope::byWorker(SupportTicket::query(), $actor)
                        ->whereIn('status', [
                            TicketStatus::Open->value,
                            TicketStatus::InProgress->value,
                        ])->count(),

                    'settlement_pending' => PortalScope::byWorker(SettlementRequest::query(), $actor)
                        ->where('status', '!=', SettlementStatus::Done->value)->count(),

                    // 아직 정원을 못 채운 수요 (매칭할 일이 남은 건)
                    'demands_open' => $this->openDemandCount($actor),

                    // 곧 도착하는 입국 건
                    'arrivals_soon' => ArrivalRecord::query()
                        ->whereHas('placement', fn ($p) => PortalScope::placements($p, $actor))
                        ->where('status', '!=', ArrivalStatus::HandedOver->value)
                        ->whereNotNull('scheduled_arrival_at')
                        ->whereBetween('scheduled_arrival_at', [
                            now(),
                            now()->addDays(self::ARRIVAL_SOON_DAYS),
                        ])->count(),
                ],

                // ── 현황 ────────────────────────────────────────────────
                'status' => [
                    'workers_active' => PortalScope::workers(Worker::query(), $actor)
                        ->where('status', WorkerStatus::Active->value)->count(),

                    'placements_confirmed' => PortalScope::placements(Placement::query(), $actor)
                        ->where('status', PlacementStatus::Confirmed->value)->count(),

                    // 이번 달 방문 점검 — 현장 관리가 돌고 있는지 보는 지표
                    'visits_this_month' => FarmVisit::query()
                        ->whereHas('farm', fn ($f) => PortalScope::farms($f, $actor))
                        ->whereBetween('visited_on', [
                            now()->startOfMonth()->toDateString(),
                            now()->endOfMonth()->toDateString(),
                        ])->count(),

                    // 고위험 평가 — 이탈 징후가 있는 근로자
                    'high_risk' => PortalScope::byWorker(MonthlyInterview::query(), $actor)
                        ->where('risk_level', RiskLevel::High->value)
                        ->whereBetween('interviewed_on', [
                            now()->subMonth()->toDateString(),
                            now()->toDateString(),
                        ])->count(),
                ],
            ],
            'meta' => [
                'role' => $actor->primaryPortalRole()?->value,
                'role_label' => $actor->primaryPortalRole()?->label(),
                'name' => $actor->name,
                // 조회 전용 역할은 '할 일' 이 아니라 '현황' 으로 읽힌다.
                'can_decide' => $canDecide,
            ],
        ]);
    }

    /** 정원을 아직 못 채운 수요 건수. */
    private function openDemandCount(User $actor): int
    {
        $demands = DemandRequest::query()
            ->whereHas('farm', fn ($f) => PortalScope::farms($f, $actor))
            ->whereIn('status', [
                DemandStatus::Submitted->value,
                DemandStatus::Aggregated->value,
                DemandStatus::LetterIssued->value,
            ])
            ->get(['id', 'farm_id', 'headcount']);

        if ($demands->isEmpty()) {
            return 0;
        }

        // 농가별 배정 인원을 한 번에 세어 N+1 을 피한다.
        $filled = Placement::query()
            ->whereIn('farm_id', $demands->pluck('farm_id')->unique())
            ->whereIn('status', [
                PlacementStatus::Proposed->value,
                PlacementStatus::Confirmed->value,
            ])
            ->selectRaw('farm_id, count(*) as c')
            ->groupBy('farm_id')
            ->pluck('c', 'farm_id');

        return $demands
            ->filter(fn ($d) => (int) ($filled[$d->farm_id] ?? 0) < (int) $d->headcount)
            ->count();
    }
}
