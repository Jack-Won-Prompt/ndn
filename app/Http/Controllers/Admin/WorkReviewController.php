<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Monitoring\Actions\RecordWorkReviewAction;
use App\Domains\Monitoring\Enums\WorkReviewResult;
use App\Domains\Monitoring\Enums\WorkReviewSection;
use App\Domains\Monitoring\Enums\WorkReviewType;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Monitoring\Models\WorkReviewItem;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Reporting\Actions\GenerateWorkReviewPdfAction;
use App\Domains\Reporting\Actions\ShareWorkReviewsAction;
use App\Domains\Reporting\Models\WorkReviewShare;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use App\Shared\Support\SignatureImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 근무상태 종합 점검표 — 콘솔 목록·작성·상세.
 *
 * 근로자 한 사람에 대한 점검이다. 농가·근로자 인적사항은 점검표에 옮겨 담지 않고
 * 화면에서 이어 붙인다(여권번호 등 암호화 필드를 복사해 두지 않기 위해서, §7-1).
 */
class WorkReviewController extends Controller
{
    /** 최근 점검부터. N+1 방지로 관계를 즉시 로딩한다. */
    public static function rows(): array
    {
        return WorkReview::query()
            ->with(['worker', 'farm.city', 'inspector'])
            ->latest('reviewed_at')->latest('id')
            ->limit(1000)
            ->get()
            ->map(fn (WorkReview $r) => [
                'id' => $r->id,
                'worker' => $r->worker?->name ?? '—',
                'nationality' => $r->worker?->nationality ?? '—',
                'city' => $r->farm?->city?->name ?? '—',
                'farm' => $r->farm?->name ?? '—',
                'date' => LocalTime::format($r->reviewed_at),
                'type' => $r->review_type->label(),
                'result' => $r->result->label(),
                'risk' => $r->risk_level->label(),
                'risk_level' => $r->risk_level->value,
                'score' => $r->risk_score,
                'inspector' => $r->inspector?->name ?? '—',
                // 제출 자료라 서명이 몇 칸 들어왔는지 목록에서 바로 보여 준다.
                'signs' => $r->signatureCount().' / '.count(WorkReview::SIGNATURE_ROLES),
                'recheck' => $r->recheck_on?->format('Y-m-d') ?? '—',
                'report' => match (true) {
                    $r->report_city && $r->report_immigration => '지자체·출입국',
                    $r->report_city => '지자체',
                    $r->report_immigration => '출입국',
                    default => '—',
                },
            ])
            ->all();
    }

    /** 점검 대상 근로자 — 배정된 농가를 함께 준다(점검표의 농가가 된다). */
    public static function workerOptions(): array
    {
        return Worker::query()
            ->where('status', WorkerStatus::Active->value)
            ->with(['placements' => function ($q) {
                $q->where('status', PlacementStatus::Confirmed->value)->latest('id')->with('farm');
            }])
            ->orderBy('name')->limit(2000)->get(['id', 'name', 'nationality'])
            ->map(function (Worker $w) {
                $farm = $w->placements->first()?->farm;

                return [
                    'value' => $w->id,
                    'label' => $w->name.' ('.$w->nationality.')'.($farm ? ' · '.$farm->name : ' · 미배정'),
                    'farm_id' => $farm?->id,
                ];
            })
            ->all();
    }

    /**
     * 영역별 항목과 보기 — 점검 입력 화면이 이대로 그린다.
     *
     * @return list<array{key: string, label: string, options: array<string,string>, items: list<array{id:int,label:string}>}>
     */
    public static function sections(): array
    {
        $items = WorkReviewItem::query()->active()->get()->groupBy(fn (WorkReviewItem $i) => $i->section->value);

        return collect(WorkReviewSection::ordered())
            ->map(fn (WorkReviewSection $s) => [
                'key' => $s->value,
                'label' => $s->label(),
                'options' => $s->options(),
                'items' => ($items->get($s->value) ?? collect())
                    ->map(fn (WorkReviewItem $i) => ['id' => $i->id, 'label' => $i->label])
                    ->values()->all(),
            ])
            ->all();
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return collect(WorkReviewType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
    }

    /** @return array<string, string> */
    public static function resultOptions(): array
    {
        return collect(WorkReviewResult::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all();
    }

    public function store(Request $request, RecordWorkReviewAction $action): JsonResponse
    {
        $data = $request->validate([
            'worker_id' => ['required', 'integer', 'exists:workers,id'],
            'farm_visit_id' => ['nullable', 'integer', 'exists:farm_visits,id'],
            'reviewed_at' => ['required', 'date'],
            'place' => ['nullable', 'string', 'max:200'],
            'review_type' => ['required', Rule::enum(WorkReviewType::class)],

            'overtime_done' => ['nullable', 'boolean'],
            'overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'overtime_consented' => ['nullable', 'boolean'],

            'avg_monthly_wage' => ['nullable', 'string', 'max:50'],
            'last_paid_on' => ['nullable', 'date'],
            'wage_unpaid' => ['nullable', 'boolean'],
            'board_provided' => ['nullable', 'boolean'],
            'contract_followed' => ['nullable', 'boolean'],
            'contract_violation' => ['nullable', 'string', 'max:2000'],

            'result' => ['required', Rule::enum(WorkReviewResult::class)],
            'notable' => ['nullable', 'string', 'max:2000'],
            'improvements' => ['nullable', 'string', 'max:2000'],
            'farm_requests' => ['nullable', 'string', 'max:2000'],

            'action_due_on' => ['nullable', 'date'],
            'action_assignee' => ['nullable', 'string', 'max:100'],
            'recheck_on' => ['nullable', 'date'],
            'report_city' => ['nullable', 'boolean'],
            'report_immigration' => ['nullable', 'boolean'],
            'action_note' => ['nullable', 'string', 'max:2000'],

            'signed_inspector' => ['nullable', 'string', 'max:100'],
            'signed_farm' => ['nullable', 'string', 'max:100'],
            'signed_worker' => ['nullable', 'string', 'max:100'],
            'signed_interpreter' => ['nullable', 'string', 'max:100'],

            'answers' => ['nullable', 'array'],

            // §12 서명란 — 서명 캔버스가 만든 base64 PNG
            'signatures' => ['nullable', 'array'],
            'signatures.*' => ['nullable', 'string'],
        ]);

        $worker = Worker::findOrFail($data['worker_id']);

        try {
            $review = $action->execute(
                $worker,
                Auth::user(),
                $data,
                $data['answers'] ?? [],
                (array) $request->input('signatures', []),
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'id' => $review->id,
            'risk' => $review->risk_level->label(),
            'score' => $review->risk_score,
        ]);
    }

    /**
     * 관계기관 제출 이력 — 발송 묶음(batch) 단위로 접어서 보여 준다.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function shareRows(): array
    {
        return WorkReviewShare::query()
            ->with(['review.worker', 'sender'])
            ->latest('sent_at')->latest('id')
            ->limit(1000)
            ->get()
            ->groupBy(fn (WorkReviewShare $s) => $s->batch_id.'|'.$s->recipient_email)
            ->map(function ($group) {
                /** @var WorkReviewShare $first */
                $first = $group->first();

                return [
                    'id' => $first->id,
                    'sent_at' => LocalTime::format($first->sent_at),
                    'org' => $first->recipient_org ?: '—',
                    'email' => $first->recipient_email,
                    'count' => $group->count(),
                    // 어떤 점검표가 나갔는지 — 번호로 되짚는다(이름은 목록에 늘어놓지 않는다).
                    'reviews' => $group->map(fn (WorkReviewShare $s) => '#'.$s->work_review_id)->implode(', '),
                    'note' => $first->note ?: '—',
                    'sender' => $first->sender?->name ?? '—',
                ];
            })
            ->values()
            ->all();
    }

    /** 최근 보낸 곳 — 기관 주소를 매번 손으로 치지 않게. */
    public static function recentRecipients(): array
    {
        return WorkReviewShare::query()
            ->latest('sent_at')
            ->limit(200)
            ->get(['recipient_email', 'recipient_org'])
            ->unique('recipient_email')
            ->take(10)
            ->map(fn (WorkReviewShare $s) => [
                'email' => $s->recipient_email,
                'org' => $s->recipient_org,
            ])
            ->values()
            ->all();
    }

    /**
     * 선택한 점검표를 관계기관에 이메일로 제출한다.
     *
     * 첨부 PDF 에는 인적사항이 들어가고 메일 본문에는 들어가지 않는다 — 왜 그렇게
     * 갈랐는지는 ShareWorkReviewsAction 주석에 적어 뒀다.
     */
    public function share(Request $request, ShareWorkReviewsAction $action): JsonResponse
    {
        $data = $request->validate([
            'review_ids' => ['required', 'array', 'min:1'],
            'review_ids.*' => ['integer', 'exists:work_reviews,id'],
            'recipients' => ['required', 'array', 'min:1'],
            'recipients.*.email' => ['required', 'email', 'max:190'],
            'recipients.*.org' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:1000'],
            // 제출 근거를 확인했다는 담당자 체크 — 기록에 남는다.
            'acknowledged' => ['accepted'],
        ]);

        try {
            $result = $action->execute(
                $data['review_ids'],
                $data['recipients'],
                $data['note'] ?? null,
                Auth::user(),
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $result);
    }

    /** 상세 — 항목별 응답까지. */
    public function show(WorkReview $workReview): JsonResponse
    {
        $workReview->load(['worker', 'farm.city', 'inspector', 'answers.item']);

        $answers = $workReview->answers
            ->groupBy(fn ($a) => $a->item?->section->value ?? 'unknown')
            ->map(fn ($group) => $group->map(fn ($a) => [
                'label' => $a->item?->label ?? '—',
                'value' => $a->item?->section->options()[$a->value] ?? $a->value,
                'bad' => $a->item?->scored && $a->item->isBad($a->value),
                'note' => $a->note,
            ])->values()->all());

        return response()->json([
            'id' => $workReview->id,
            'worker' => $workReview->worker?->name,
            'nationality' => $workReview->worker?->nationality,
            'farm' => $workReview->farm?->name,
            'city' => $workReview->farm?->city?->name,
            'inspector' => $workReview->inspector?->name,
            'reviewed_at' => LocalTime::format($workReview->reviewed_at),
            'place' => $workReview->place,
            'type' => $workReview->review_type->label(),
            'result' => $workReview->result->label(),
            'risk' => $workReview->risk_level->label(),
            'score' => $workReview->risk_score,
            'overtime' => [
                'done' => $workReview->overtime_done,
                'hours' => $workReview->overtime_hours,
                'consented' => $workReview->overtime_consented,
            ],
            'wage' => [
                'avg' => $workReview->avg_monthly_wage,
                'last_paid_on' => $workReview->last_paid_on?->format('Y-m-d'),
                'unpaid' => $workReview->wage_unpaid,
                'board_provided' => $workReview->board_provided,
                'contract_followed' => $workReview->contract_followed,
                'violation' => $workReview->contract_violation,
            ],
            'opinion' => [
                'notable' => $workReview->notable,
                'improvements' => $workReview->improvements,
                'farm_requests' => $workReview->farm_requests,
            ],
            'actions' => [
                'due_on' => $workReview->action_due_on?->format('Y-m-d'),
                'assignee' => $workReview->action_assignee,
                'recheck_on' => $workReview->recheck_on?->format('Y-m-d'),
                'report_city' => $workReview->report_city,
                'report_immigration' => $workReview->report_immigration,
                'note' => $workReview->action_note,
            ],
            // 이름과 서명 이미지를 한 묶음으로 준다. 이름만 있고 서명이 없으면
            // 화면에서 '서명 없음'으로 드러나야 한다 — 제출 자료라 중요하다.
            'signatures' => collect(WorkReview::SIGNATURE_ROLES)
                ->map(fn (array $def, string $role) => [
                    'role' => $role,
                    'label' => $def[2],
                    'name' => $workReview->{$def[0]},
                    'image_url' => $workReview->hasSignature($role)
                        ? route('admin.work-reviews.signature', [$workReview, $role])
                        : null,
                ])
                ->values()
                ->all(),
            'sections' => collect(WorkReviewSection::ordered())
                ->map(fn (WorkReviewSection $s) => [
                    'label' => $s->label(),
                    'answers' => $answers->get($s->value, []),
                ])
                ->all(),
        ]);
    }

    /**
     * 제출용 PDF (원본 서식 그대로).
     *
     * 관공서 제출 서식이라 여권번호·생년월일 같은 인적사항이 들어간다.
     * 파일로 저장하지 않고 요청할 때마다 만들어 내보낸다 — 저장하면 암호화
     * 필드를 평문으로 복사해 쌓아 두는 셈이 된다(§7-1).
     * 내려받는 순간 열람 기록을 남긴다(§7-6).
     */
    public function pdf(WorkReview $workReview, GenerateWorkReviewPdfAction $action): Response
    {
        $workReview->worker?->recordAccessBy(Auth::user(), 'work-review-pdf');

        return $action->pdf($workReview)->download($action->filename($workReview));
    }

    /**
     * 서명 이미지 스트리밍 (private 저장 · ndn_admin 전용 라우트).
     *
     * 서명은 본인을 특정하는 개인정보라 근로자 서명을 열면 열람 기록을 남긴다(§7-6).
     */
    public function signature(WorkReview $workReview, string $role): StreamedResponse
    {
        abort_unless(array_key_exists($role, WorkReview::SIGNATURE_ROLES), 404);

        $path = $workReview->signaturePath($role);
        abort_unless(SignatureImage::exists($path), 404);

        if ($role === 'worker') {
            $workReview->worker?->recordAccessBy(Auth::user(), 'work-review-signature');
        }

        return Storage::disk(SignatureImage::DISK)->response($path, null, [
            'Content-Type' => SignatureImage::mime($path),
        ], 'inline');
    }
}
