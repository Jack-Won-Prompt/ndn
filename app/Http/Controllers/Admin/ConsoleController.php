<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\EvaluationItem;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Reporting\Actions\GenerateMonthlyReportAction;
use App\Domains\Settlement\Actions\AssignSettlementAction;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Support\Actions\UpdateTicketStatusAction;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\AccountDeletionRequest;
use App\Domains\Support\Models\SosAlert;
use App\Domains\Support\Models\SupportTicket;
use App\Domains\Support\Services\ChatService;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Shared\Enums\UserRole;
use App\Shared\Support\LocalTime;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NDN 운영 콘솔 — MDI 탭 워크스페이스 셸 + 임베디드 업무 화면.
 *
 * 셸(shell)은 사이드바 + 탭바 + iframe 컨테이너만 렌더한다. 메뉴 클릭 시 JS 가
 * /admin/screen/{key} 를 iframe 탭으로 열어 화면 전환 없이 여러 화면을 탭으로 유지한다.
 */
class ConsoleController extends Controller
{
    /**
     * 사이드바 메뉴 정의. 각 항목이 하나의 업무 화면(탭)이 된다.
     *
     * @return array<int, array{group: string, items: array<int, array{key:string,label:string,icon:string}>}>
     */
    public static function menu(): array
    {
        return [
            [
                'group' => '',
                'items' => [
                    ['key' => 'dashboard', 'label' => '대시보드', 'icon' => 'grid'],
                ],
            ],
            [
                'group' => '수요·모집',
                'items' => [
                    ['key' => 'demand', 'label' => '수요 신청', 'icon' => 'clipboard'],
                    ['key' => 'baseinfo', 'label' => '농가·지자체', 'icon' => 'users'],
                    ['key' => 'regions', 'label' => '지역별 모집·배치', 'icon' => 'grid'],
                ],
            ],
            [
                'group' => '모집·선발',
                'items' => [
                    ['key' => 'candidates', 'label' => '후보자·평가', 'icon' => 'clipboard'],
                ],
            ],
            [
                'group' => '근로자',
                'items' => [
                    ['key' => 'workers', 'label' => '근로자', 'icon' => 'users'],
                    ['key' => 'signups', 'label' => '가입 승인', 'icon' => 'inbox'],
                    ['key' => 'onboarding', 'label' => '온보딩 검수', 'icon' => 'inbox'],
                    ['key' => 'required-documents', 'label' => '필수 동의 문서', 'icon' => 'clipboard'],
                ],
            ],
            [
                'group' => '정착·사후관리',
                'items' => [
                    ['key' => 'settlement', 'label' => '정착 처리보드', 'icon' => 'grid'],
                    ['key' => 'monitoring', 'label' => '월별 점검', 'icon' => 'clipboard'],
                    ['key' => 'life-checklist', 'label' => '생활 체크리스트', 'icon' => 'clipboard'],
                    ['key' => 'work-reviews', 'label' => '근무상태 점검표', 'icon' => 'clipboard'],
                    ['key' => 'farmvisits', 'label' => '농가 방문 점검', 'icon' => 'clipboard'],
                    ['key' => 'tickets', 'label' => '민원', 'icon' => 'inbox'],
                    ['key' => 'inquiries', 'label' => '문의하기', 'icon' => 'inbox'],
                    ['key' => 'notices', 'label' => '공지사항', 'icon' => 'clipboard'],
                    ['key' => 'chat', 'label' => '채팅', 'icon' => 'inbox'],
                ],
            ],
            [
                'group' => '설정',
                'items' => [
                    ['key' => 'invitations', 'label' => '조직 초대', 'icon' => 'users'],
                    ['key' => 'account-deletions', 'label' => '계정 삭제 요청', 'icon' => 'inbox'],
                    ['key' => 'settings', 'label' => '사이트 설정', 'icon' => 'cog'],
                    ['key' => 'accesslog', 'label' => '접속 로그', 'icon' => 'inbox'],
                ],
            ],
        ];
    }

    /** 탭 워크스페이스 셸 */
    public function shell(): View
    {
        return view('admin.shell', [
            'menu' => self::menu(),
            'user' => Auth::user(),
            'badges' => self::badgeCounts(),
        ]);
    }

    /**
     * 사이드바 메뉴 배지 카운트 (조치 필요 건). 실시간 갱신은 Pusher(admin.alerts)가 담당.
     *
     * @return array<string, int>
     */
    public static function badgeCounts(): array
    {
        return [
            'inquiries' => app(ChatService::class)->unreadInquiryCount(),
            'signups' => Worker::where('status', WorkerStatus::Pending->value)->count(),
            'account-deletions' => AccountDeletionRequest::where('status', AccountDeletionRequest::STATUS_PENDING)->count(),
        ];
    }

    /** 임베디드 화면 디스패치 */
    public function screen(Request $request, string $key): View
    {
        return match ($key) {
            'dashboard' => $this->dashboard(),
            'demand' => $this->demand($request),
            'baseinfo' => $this->baseinfo(),
            'regions' => view('admin.screens.regions', ['rows' => RegionController::rows()]),
            'candidates' => $this->candidates($request),
            'workers' => $this->workers($request),
            'signups' => view('admin.screens.signups', ['rows' => SignupApprovalController::rows()]),
            'invitations' => view('admin.screens.invitations', [
                'rows' => InvitationController::rows(),
                'roleOptions' => InvitationController::roleOptions(),
            ]),
            'onboarding' => $this->onboarding($request),
            'settlement' => $this->settlement($request),
            'monitoring' => $this->monitoring($request),
            'life-checklist' => view('admin.screens.life-checklist', [
                'rows' => LifeChecklistController::rows(),
                'itemRows' => LifeChecklistController::itemRows(),
            ]),
            'work-reviews' => view('admin.screens.work-reviews', [
                'rows' => WorkReviewController::rows(),
                'workers' => WorkReviewController::workerOptions(),
                'sections' => WorkReviewController::sections(),
                'typeOptions' => WorkReviewController::typeOptions(),
                'resultOptions' => WorkReviewController::resultOptions(),
                'me' => Auth::user()?->name ?? '',
            ]),
            'farmvisits' => view('admin.screens.farmvisits', [
                'rows' => FarmVisitController::rows(),
                'farms' => FarmVisitController::farmOptions(),
                'statuses' => FarmVisitController::statusOptions(),
                'itemLabels' => FarmVisitController::itemLabels(),
            ]),
            'tickets' => $this->tickets($request),
            'account-deletions' => view('admin.screens.account-deletions', ['rows' => AccountDeletionAdminController::rows()]),
            'required-documents' => view('admin.screens.required-documents', [
                'rows' => RequiredDocumentAdminController::rows(),
            ]),
            'service-requests' => view('admin.screens.service-requests', [
                'rows' => ServiceRequestController::rows(),
                'statuses' => ServiceRequestController::statusOptions(),
            ]),
            'notices' => view('admin.screens.notices', ['rows' => NoticeController::rows()]),
            'inquiries' => view('admin.screens.inquiries'),
            'chat' => view('admin.screens.chat', ['me' => app(ChatService::class)->partyForUser(Auth::user())]),
            'settings' => $this->settingsForm(),
            'accesslog' => view('admin.screens.accesslog', [
                'rows' => AccessLogController::rows(),
                'summary' => AccessLogController::summary(),
            ]),
            default => abort(404),
        };
    }

    private function candidates(Request $request): View
    {
        return view('admin.screens.candidates', [
            'rows' => CandidateGridController::rows(),
            // 평가 체크리스트 항목 — 같은 화면의 [평가 항목] 탭에서 편집한다
            'itemRows' => EvaluationItemGridController::rows(),
            'itemsTotal' => EvaluationItem::totalMaxScore(),
        ]);
    }

    private function baseinfo(): View
    {
        return view('admin.screens.baseinfo', [
            'cityRows' => BaseInfoGridController::cityRows(),
            'farmRows' => BaseInfoGridController::farmRows(),
            'cityOptions' => City::orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
        ]);
    }

    private function settlement(Request $request): View
    {
        // 칸반 단계별 그룹
        $all = SettlementRequest::with('worker')->latest('id')->get();
        $stages = SettlementStatus::cases();

        return view('admin.screens.settlement', [
            'all' => $all,
            'stages' => $stages,
            'agencies' => self::agencyOptions(),
        ]);
    }

    /**
     * 배정 가능한 제휴 대리점 목록 [assigned_agency_id => 표시명].
     * 별도 대리점 테이블이 없으므로 partner_agency 사용자에서 도출한다.
     *
     * @return array<int, string>
     */
    private static function agencyOptions(): array
    {
        return User::whereNotNull('assigned_agency_id')
            ->whereHas('roles', fn ($q) => $q->where('name', UserRole::PartnerAgency->value))
            ->orderBy('name')
            ->get(['name', 'assigned_agency_id'])
            ->mapWithKeys(fn (User $u) => [$u->assigned_agency_id => $u->name])
            ->all();
    }

    /** 정착 건을 대리점에 배정 (§7-4 동의 없으면 Action 이 거부). */
    public function assignSettlement(Request $request, SettlementRequest $settlement, AssignSettlementAction $action): JsonResponse
    {
        $data = $request->validate([
            'agency_id' => ['required', 'integer'],
        ]);

        try {
            $action->execute($settlement, (int) $data['agency_id']);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true]);
    }

    private function monitoring(Request $request): View
    {
        // 근로자 소속(시·농가) = 확정 배정 → 농가 → 시. N+1 방지로 즉시 로딩.
        $rows = MonthlyInterview::with(['worker.placements' => function ($q) {
            $q->where('status', PlacementStatus::Confirmed->value)->latest('id')->with('farm.city');
        }])->latest('id')->limit(1000)->get()
            ->map(function (MonthlyInterview $iv) {
                $placement = $iv->worker?->placements->first();
                $farm = $placement?->farm;

                return [
                    'id' => $iv->id,
                    'worker' => $iv->worker?->name ?? '—',
                    'city' => $farm?->city?->name ?? '—',
                    'farm' => $farm?->name ?? '—',
                    'date' => $iv->interviewed_on?->format('Y-m-d'),
                    'pay' => $iv->pay_received ? '양호' : '이상',
                    'discrim' => $iv->no_discrimination ? '양호' : '이상',
                    'rules' => $iv->follows_rules ? '양호' : '이상',
                    'group' => $iv->adapts_group ? '양호' : '이상',
                    'health' => $iv->health_ok ? '양호' : '이상',
                    'flight' => $iv->no_flight_signs ? '양호' : '이상',
                    'risk' => match ($iv->risk_level?->value) {
                        'high' => '고위험', 'medium' => '주의', default => '낮음'
                    },
                    'memo' => $iv->memo,
                ];
            })->all();

        return view('admin.screens.monitoring', [
            'rows' => $rows,
            'workers' => MonitoringController::workerOptions(),
            'itemLabels' => MonitoringController::itemLabels(),
        ]);
    }

    private function tickets(Request $request): View
    {
        return view('admin.screens.tickets', ['rows' => TicketGridController::rows()]);
    }

    /** 민원 상태 인라인 편집 저장 (그리드 셀 편집) */
    public function updateTicketStatus(
        Request $request,
        SupportTicket $ticket,
        UpdateTicketStatusAction $action,
    ): JsonResponse {
        $data = $request->validate([
            'status' => ['required', Rule::enum(TicketStatus::class)],
        ]);

        try {
            $action->execute(
                $ticket,
                TicketStatus::from($data['status']),
                $request->user(),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'status' => $ticket->fresh()->status->value]);
    }

    /** 사이트 설정 편집 폼 */
    private function settingsForm(): View
    {
        return view('admin.screens.settings', [
            'groups' => Setting::fields(),
            'values' => Setting::allKeyed(),
            'saved' => session('settings_saved', false),
        ]);
    }

    /** 사이트 설정 저장 */
    public function saveSettings(Request $request): RedirectResponse
    {
        foreach (Setting::allKeys() as $key) {
            // 폼 필드명은 점(.)을 언더스코어로 치환해 전송한다
            $field = str_replace('.', '__', $key);
            Setting::put($key, $request->input($field));
        }

        return redirect()
            ->to(url('admin/screen/settings'))
            ->with('settings_saved', true);
    }

    private function dashboard(): View
    {
        return view('admin.screens.dashboard', [
            'stats' => [
                'workers' => Worker::count(),
                'demand' => DemandRequest::whereIn('status', [
                    DemandStatus::Submitted->value,
                    DemandStatus::Aggregated->value,
                ])->count(),
                'onboarding' => OnboardingSubmission::where('status', OnboardingStatus::Submitted->value)->count(),
                'sos' => SosAlert::where('status', 'open')->count(),
            ],
        ]);
    }

    private function demand(Request $request): View
    {
        return view('admin.screens.demand', [
            'rows' => DemandGridController::rows(),
            'farms' => Farm::orderBy('name')->get(['id', 'name'])
                ->map(fn ($f) => ['value' => $f->id, 'label' => $f->name])->all(),
            'cities' => City::orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])->all(),
        ]);
    }

    private function workers(Request $request): View
    {
        return view('admin.screens.workers', [
            'rows' => WorkerGridController::rows(),
            // 지원 지자체 선택지 (그리드 콤보). 지역별 모집·배치를 나눠 보기 위한 값이다.
            'cityOptions' => City::orderBy('region')->orderBy('name')->get()
                ->map(fn (City $c) => ['value' => $c->id, 'label' => trim(($c->region ?? '').' '.$c->name)])
                ->all(),
        ]);
    }

    /** 근로자 상세 — 개인정보 열람 감사 로그 (CLAUDE.md §7-6) */
    public function worker(Request $request, Worker $worker): View|JsonResponse
    {
        // 팝업(모달) 조회도 개인정보 열람이므로 동일하게 감사 로그를 남긴다.
        $worker->recordAccessBy(Auth::user(), 'console-detail');

        // 지원 지자체는 상세에 표시된다 — 명시적으로 읽는다(§11: preventLazyLoading).
        $worker->loadMissing('city');

        // 상세 팝업/탭용 JSON (민감 필드 여권번호·생년월일·전화번호는 §7 에 따라 제외)
        if ($request->query('format') === 'json' || $request->wantsJson()) {
            // 소속·입국·생활점검
            $placement = $worker->placements()
                ->where('status', PlacementStatus::Confirmed->value)
                ->with(['farm.city', 'arrival'])->latest('id')->first();
            $farm = $placement?->farm;
            $arrival = $placement?->arrival;

            $interviews = MonthlyInterview::where('worker_id', $worker->id)
                ->latest('interviewed_on')->latest('id')->limit(24)->get()
                ->map(fn (MonthlyInterview $iv) => [
                    'date' => $iv->interviewed_on?->format('Y-m-d'),
                    'source' => $iv->source?->label() ?? '—',
                    'risk' => $iv->risk_level?->label() ?? '—',
                    'risk_level' => $iv->risk_level?->value ?? 'low',
                    'negatives' => collect(MonthlyInterview::ITEMS)->filter(fn ($it) => ! $iv->{$it})->count(),
                ])->all();

            return response()->json([
                'id' => $worker->id,
                'name' => $worker->name,
                'nationality' => $worker->nationality,
                'locale' => $worker->locale,
                'status' => $worker->status->value,
                'created' => LocalTime::format($worker->created_at),
                // 지원 지역(가입 시 선택) 과 실제 배치 지역은 다를 수 있어 따로 보여준다
                'applied_city' => $worker->city?->name,
                'city' => $farm?->city?->name,
                'farm' => $farm?->name,
                'arrival' => $arrival ? [
                    'status' => $arrival->status->label(),
                    'flight_no' => $arrival->flight_no,
                    'airport' => $arrival->airport,
                    'scheduled' => LocalTime::format($arrival->scheduled_arrival_at),
                ] : null,
                'interviews' => $interviews,
            ]);
        }

        return view('admin.screens.worker_detail', ['worker' => $worker]);
    }

    private function onboarding(Request $request): View
    {
        $rows = OnboardingSubmission::with('worker')->latest('id')->limit(1000)->get()
            ->map(fn (OnboardingSubmission $o) => [
                'id' => $o->id,
                'worker' => $o->worker?->name ?? '—',
                'status' => $o->status->label(),
                'submitted' => LocalTime::format($o->submitted_at) ?? '—',
                'note' => $o->review_note ?? '—',
            ])->all();

        return view('admin.screens.onboarding', ['rows' => $rows]);
    }

    /**
     * 온보딩 제출물 상세 (검수 팝업용). 본인 기입 payload 는 개인정보이므로
     * 열람 시 감사 로그를 남긴다 (CLAUDE.md §7-6).
     */
    public function onboardingDetail(OnboardingSubmission $submission): JsonResponse
    {
        $submission->loadMissing('worker');
        $submission->worker?->recordAccessBy(Auth::user(), 'onboarding-detail');

        $hasSignature = filled($submission->signature_path)
            && Storage::disk('local')->exists($submission->signature_path);

        return response()->json([
            'id' => $submission->id,
            'worker' => $submission->worker?->name ?? '—',
            'status' => $submission->status->label(),
            'submitted_at' => LocalTime::format($submission->submitted_at) ?? '—',
            'review_note' => $submission->review_note,
            'payload' => $submission->payload ?? [],
            'has_signature' => $hasSignature,
            'signature_url' => $hasSignature
                ? route('admin.onboarding.signature', $submission)
                : null,
        ]);
    }

    /** 전자서명 파일 스트리밍 (private 스토리지 · 열람 감사 로그). */
    public function onboardingSignature(OnboardingSubmission $submission): StreamedResponse
    {
        abort_unless(
            filled($submission->signature_path) && Storage::disk('local')->exists($submission->signature_path),
            404,
        );

        $submission->loadMissing('worker');
        $submission->worker?->recordAccessBy(Auth::user(), 'onboarding-signature');

        return Storage::disk('local')->response($submission->signature_path);
    }

    /** 지자체 월간 보고서 PDF 다운로드 (업무흐름 §10) */
    public function monthlyReport(Request $request, GenerateMonthlyReportAction $action): Response
    {
        $year = (int) $request->integer('year', (int) now()->year);
        $month = (int) $request->integer('month', (int) now()->month);

        $pdf = $action->pdf($year, $month);

        return $pdf->download("ndn-monthly-{$year}-{$month}.pdf");
    }
}
