<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Enums\WorkerFileType;
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
use App\Domains\Support\Models\WorkerExit;
use App\Domains\Support\Services\ChatService;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use App\Shared\Enums\UserRole;
use App\Shared\Support\DeployState;
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
                    ['key' => 'matching', 'label' => '농가 매칭·배정', 'icon' => 'users'],
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
                    // 긴급 대응이라 이 그룹 맨 위에 둔다.
                    ['key' => 'sos', 'label' => '긴급 SOS', 'icon' => 'inbox'],
                    ['key' => 'settlement', 'label' => '정착 처리보드', 'icon' => 'grid'],
                    ['key' => 'life-checklist', 'label' => '생활 체크리스트', 'icon' => 'clipboard'],
                    ['key' => 'work-reviews', 'label' => '근무상태 점검표', 'icon' => 'clipboard'],
                    ['key' => 'farmvisits', 'label' => '농가 방문 점검', 'icon' => 'clipboard'],
                    ['key' => 'tickets', 'label' => '민원', 'icon' => 'inbox'],
                    ['key' => 'exits', 'label' => '조기귀국·이탈', 'icon' => 'inbox'],
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
            // 배포가 덜 끝났으면 띠로 알린다 (§배포: 같은 원인으로 장애 세 번).
            'deployProblems' => DeployState::problems(),
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
            // 아직 아무도 확인하지 않은 긴급 요청 — 가장 먼저 눈에 띄어야 한다.
            'sos' => SosController::openCount(),
            'inquiries' => app(ChatService::class)->unreadInquiryCount(),
            'signups' => SignupApprovalController::openCount(),
            // 결정이 안 난 조기귀국·소재 불명 — 오래 방치되면 신고 시기를 놓친다.
            'exits' => WorkerExitController::openCount(),
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
            'matching' => view('admin.screens.matching', [
                'rows' => MatchingController::rows(),
                'placements' => MatchingController::placementRows(),
                // 농가를 이 화면에서 바로 등록·수정할 수 있게 한다(기준정보와 같은 표).
                'farmRows' => MatchingController::farmRows(),
                'cityOptions' => BaseInfoGridController::cityOptions(),
            ]),
            'workers' => $this->workers($request),
            'signups' => view('admin.screens.signups', [
                'rows' => SignupApprovalController::rows(),
                'supplementItems' => SignupApprovalController::supplementItems(),
            ]),
            'invitations' => view('admin.screens.invitations', [
                'rows' => InvitationController::rows(),
                'roleOptions' => InvitationController::roleOptions(),
            ]),
            'onboarding' => $this->onboarding($request),
            'settlement' => $this->settlement($request),
            'sos' => $this->sos(),
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
                'shares' => WorkReviewController::shareRows(),
                'recentRecipients' => WorkReviewController::recentRecipients(),
            ]),
            'farmvisits' => view('admin.screens.farmvisits', [
                'rows' => FarmVisitController::rows(),
                'farms' => FarmVisitController::farmOptions(),
                'statuses' => FarmVisitController::statusOptions(),
            ]),
            'tickets' => $this->tickets($request),
            'exits' => view('admin.screens.exits', [
                'rows' => WorkerExitController::rows(),
                'workers' => WorkerExitController::workerOptions(),
                'typeOptions' => WorkerExitController::typeOptions(),
                'reasonOptions' => WorkerExitController::reasonOptions(),
                'pendingTickets' => WorkerExitController::pendingTickets(),
            ]),
            'account-deletions' => view('admin.screens.account-deletions', ['rows' => AccountDeletionAdminController::rows()]),
            'required-documents' => view('admin.screens.required-documents', [
                'rows' => RequiredDocumentAdminController::rows(),
            ]),
            'service-requests' => view('admin.screens.service-requests', [
                'rows' => ServiceRequestController::rows(),
                'statuses' => ServiceRequestController::statusOptions(),
            ]),
            'notices' => view('admin.screens.notices', [
                'rows' => NoticeController::rows(),
                'targetOptions' => NoticeController::targetOptions(),
                'nationalityOptions' => NoticeController::nationalityOptions(),
                'statusOptions' => NoticeController::statusOptions(),
                'workers' => NoticeController::workerOptions(),
                'appUsers' => NoticeController::appUserCount(),
            ]),
            'inquiries' => view('admin.screens.inquiries'),
            'chat' => view('admin.screens.chat', ['me' => app(ChatService::class)->partyForUser(Auth::user())]),
            'settings' => $this->settingsForm(),
            'accesslog' => view('admin.screens.accesslog', [
                'rows' => AccessLogController::rows(),
                'summary' => AccessLogController::summary(),
                'byCountry' => AccessLogController::byCountry(),
                'displayTz' => AccessLogController::displayTz(),
                'hasGeoData' => AccessLogController::hasGeoData(),
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
            'cityOptions' => BaseInfoGridController::cityOptions(),
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

    /** 긴급 SOS 상황판 — 목록에 근로자 이름이 보이므로 열람 기록을 남긴다(§7-6). */
    private function sos(): View
    {
        $rows = SosController::rows();
        SosController::logAccess(Auth::user(), $rows);

        return view('admin.screens.sos', [
            'rows' => $rows,
            'openCount' => SosController::openCount(),
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

        // 상세 팝업/탭용 JSON.
        //
        // 여권번호·생년월일·본국 전화를 **그대로 보여 준다.** 본사 담당자는 이 값으로
        // 관공서 서류를 만들고 항공권을 끊는다 — 가려 두면 결국 다른 곳(엑셀·메신저)에
        // 옮겨 적게 되고, 그쪽이 훨씬 위험하다.
        //
        // §7-1 이 요구하는 마스킹은 **로그·예외 메시지**다(MasksSensitiveData 가 계속
        // 담당한다). 화면 노출은 §7-6 의 열람 기록으로 통제한다 — 아래 recordAccessBy 가
        // 누가·언제·어떤 근로자를 봤는지 남긴다.
        if ($request->query('format') === 'json' || $request->wantsJson()) {
            // 소속·입국·생활점검
            $placement = $worker->placements()
                ->where('status', PlacementStatus::Confirmed->value)
                ->with(['farm.city', 'arrival'])->latest('id')->first();
            $farm = $placement?->farm;
            $arrival = $placement?->arrival;

            // 점검 이력 — 월별 인터뷰 6항목이 있던 자리다. 근무상태 종합 점검표로 바뀌었다.
            $reviews = WorkReview::where('worker_id', $worker->id)
                ->latest('reviewed_at')->latest('id')->limit(24)->get()
                ->map(fn (WorkReview $r) => [
                    'date' => $r->reviewed_at?->timezone(config('ndn.timezone'))->format('Y-m-d'),
                    'type' => $r->review_type->label(),
                    'result' => $r->result->label(),
                    'risk' => $r->risk_level->label(),
                    'risk_level' => $r->risk_level->value,
                    'score' => $r->risk_score,
                ])->all();

            return response()->json([
                'id' => $worker->id,
                'name' => $worker->name,
                'nationality' => $worker->nationality,
                'locale' => $worker->locale,
                'status' => $worker->status->value,
                // 민감 필드 — 열람 기록을 남기고 그대로 내보낸다(§7-6).
                'passport_no' => $worker->passport_no,
                'birth_date' => $worker->birth_date,
                'phone_home_country' => $worker->phone_home_country,
                'gender' => $worker->gender?->label(),
                'age' => $worker->age(),
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
                'reviews' => $reviews,
                // 조기 귀국·이탈 이력 — 계정 상태만 봐서는 왜 그렇게 됐는지 알 수 없다.
                'exits' => $worker->exits()->with('decider:id,name')->latest('id')->limit(10)->get()
                    ->map(fn (WorkerExit $e) => [
                        'id' => $e->id,
                        'type' => $e->type->label(),
                        'status' => $e->status->label(),
                        'tone' => $e->status->tone(),
                        'reason' => $e->reason->label(),
                        'date' => $e->occurred_on?->toDateString(),
                        'label' => $e->type->occurredLabel(),
                        'decided_by' => $e->decider?->name,
                    ])->all(),
                // 본사가 보관하는 개인 서류 (여권 사본·건강검진 등)
                'files' => WorkerFileController::rows($worker),
                'file_types' => WorkerFileType::options(),
                'file_upload_url' => route('admin.workers.files.store', $worker),
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
