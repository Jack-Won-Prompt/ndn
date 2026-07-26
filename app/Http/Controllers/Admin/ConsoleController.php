<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Demand\Enums\DemandStatus;
use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Models\Farm;
use App\Domains\Monitoring\Models\MonthlyInterview;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Reporting\Actions\GenerateMonthlyReportAction;
use App\Domains\Settlement\Enums\SettlementStatus;
use App\Domains\Settlement\Models\SettlementRequest;
use App\Domains\Support\Actions\UpdateTicketStatusAction;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SosAlert;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use App\Models\Setting;
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
                    ['key' => 'onboarding', 'label' => '온보딩 검수', 'icon' => 'inbox'],
                ],
            ],
            [
                'group' => '정착·사후관리',
                'items' => [
                    ['key' => 'settlement', 'label' => '정착 처리보드', 'icon' => 'grid'],
                    ['key' => 'monitoring', 'label' => '월별 점검', 'icon' => 'clipboard'],
                    ['key' => 'tickets', 'label' => '민원', 'icon' => 'inbox'],
                ],
            ],
            [
                'group' => '설정',
                'items' => [
                    ['key' => 'settings', 'label' => '사이트 설정', 'icon' => 'cog'],
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
        ]);
    }

    /** 임베디드 화면 디스패치 */
    public function screen(Request $request, string $key): View
    {
        return match ($key) {
            'dashboard' => $this->dashboard(),
            'demand' => $this->demand($request),
            'candidates' => $this->candidates($request),
            'workers' => $this->workers($request),
            'onboarding' => $this->onboarding($request),
            'settlement' => $this->settlement($request),
            'monitoring' => $this->monitoring($request),
            'tickets' => $this->tickets($request),
            'settings' => $this->settingsForm(),
            default => abort(404),
        };
    }

    private function candidates(Request $request): View
    {
        return view('admin.screens.candidates', ['rows' => CandidateGridController::rows()]);
    }

    private function settlement(Request $request): View
    {
        // 칸반 단계별 그룹
        $all = SettlementRequest::with('worker')->latest('id')->get();
        $stages = SettlementStatus::cases();

        return view('admin.screens.settlement', ['all' => $all, 'stages' => $stages]);
    }

    private function monitoring(Request $request): View
    {
        $rows = MonthlyInterview::with('worker')->latest('id')->limit(1000)->get();

        return view('admin.screens.monitoring', ['rows' => $rows]);
    }

    private function tickets(Request $request): View
    {
        $rows = SupportTicket::with('worker')->latest('id')->limit(1000)->get();

        return view('admin.screens.tickets', ['rows' => $rows]);
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
        return view('admin.screens.workers', ['rows' => WorkerGridController::rows()]);
    }

    /** 근로자 상세 — 개인정보 열람 감사 로그 (CLAUDE.md §7-6) */
    public function worker(Request $request, Worker $worker): View|JsonResponse
    {
        // 팝업(모달) 조회도 개인정보 열람이므로 동일하게 감사 로그를 남긴다.
        $worker->recordAccessBy(Auth::user(), 'console-detail');

        // 상세 팝업용 JSON (민감 필드 여권번호·생년월일·전화번호는 §7 에 따라 제외)
        if ($request->query('format') === 'json' || $request->wantsJson()) {
            return response()->json([
                'id' => $worker->id,
                'name' => $worker->name,
                'nationality' => $worker->nationality,
                'locale' => $worker->locale,
                'status' => $worker->status,
                'created' => $worker->created_at?->format('Y-m-d H:i'),
            ]);
        }

        return view('admin.screens.worker_detail', ['worker' => $worker]);
    }

    private function onboarding(Request $request): View
    {
        $rows = OnboardingSubmission::with('worker')->latest('id')->limit(1000)->get();

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
            'submitted_at' => $submission->submitted_at?->format('Y-m-d H:i') ?? '—',
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
