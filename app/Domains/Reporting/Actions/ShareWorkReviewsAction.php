<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Actions;

use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Reporting\Models\WorkReviewShare;
use App\Mail\WorkReviewShareMail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * 근무상태 점검표를 관계기관에 이메일로 제출한다 (업무흐름 §6 — 관계기관 공유).
 *
 * 원본 서식 각주: "관할 지자체 및 관계기관의 요청 시 제출할 수 있습니다."
 *
 * 개인정보를 다루는 방식이 이 Action 의 핵심이다.
 *   - **첨부 PDF 에는** 여권번호·생년월일이 들어간다. 관공서 제출 서식이라
 *     비워 두면 제출이 되지 않는다.
 *   - **메일 본문·첨부 파일명에는** 넣지 않는다(§7-3). 파일명은 메일 목록에
 *     그대로 노출되므로 사람 이름 대신 점검표 번호를 쓴다.
 *   - PDF 를 디스크에 두지 않는다. 만들어서 바로 첨부하고 버린다(§7-1).
 *   - 누가·언제·무엇을·어디로 보냈는지 WorkReviewShare 에 남긴다(§7-6).
 *
 * 큐에 넣지 않고 그 자리에서 보낸다. 제출은 담당자가 결과를 확인해야 하는
 * 업무라, 실패가 큐 뒤로 숨으면 안 보낸 걸 보낸 줄 안다. 대신 건수를 제한한다.
 */
class ShareWorkReviewsAction
{
    /** 한 번에 보낼 수 있는 점검표 수 — PDF 를 그 자리에서 만들기 때문에 상한을 둔다. */
    public const MAX_REVIEWS = 10;

    /** 한 번에 보낼 수 있는 수신처 수. */
    public const MAX_RECIPIENTS = 5;

    /** 개인정보로 간주하는 패턴 (PersonalDataInNotificationTest 와 동일 기준) */
    private const PII_PATTERNS = [
        '/\b[A-Z][0-9]{7,8}\b/',                    // 여권번호
        '/\b01[0-9]-?[0-9]{3,4}-?[0-9]{4}\b/',      // 휴대폰
        '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', // 이메일
        '/\b[0-9]{6}-?[1-4][0-9]{6}\b/',            // 주민등록번호
    ];

    public function __construct(private readonly GenerateWorkReviewPdfAction $pdf) {}

    /**
     * @param  list<int>  $reviewIds
     * @param  list<array{email: string, org?: string|null}>  $recipients
     * @return array{batch: string, reviews: int, recipients: int}
     *
     * @throws RuntimeException 건수 초과·대상 없음
     * @throws ValidationException 안내 문구에 개인정보가 섞였을 때
     */
    public function execute(array $reviewIds, array $recipients, ?string $note, User $actor): array
    {
        $reviewIds = array_values(array_unique(array_map('intval', $reviewIds)));

        if ($reviewIds === []) {
            throw new RuntimeException('공유할 점검표를 선택해 주세요.');
        }

        if (count($reviewIds) > self::MAX_REVIEWS) {
            throw new RuntimeException('한 번에 '.self::MAX_REVIEWS.'건까지 보낼 수 있습니다. 나눠서 보내 주세요.');
        }

        if ($recipients === []) {
            throw new RuntimeException('받는 곳을 한 곳 이상 입력해 주세요.');
        }

        if (count($recipients) > self::MAX_RECIPIENTS) {
            throw new RuntimeException('받는 곳은 한 번에 '.self::MAX_RECIPIENTS.'곳까지입니다.');
        }

        if (filled($note)) {
            $this->assertNoPersonalData($note);
        }

        $reviews = WorkReview::query()
            ->with(['worker', 'farm.city', 'inspector', 'answers.item'])
            ->whereIn('id', $reviewIds)
            ->orderBy('reviewed_at')
            ->get();

        if ($reviews->count() !== count($reviewIds)) {
            throw new RuntimeException('삭제됐거나 존재하지 않는 점검표가 포함돼 있습니다.');
        }

        $documents = $reviews->map(fn (WorkReview $r) => [
            'name' => $this->attachmentName($r),
            'bytes' => $this->pdf->pdf($r)->output(),
        ])->all();

        $batch = (string) Str::uuid();
        $period = $this->period($reviews);
        $sentAt = now();

        foreach ($recipients as $recipient) {
            Mail::to($recipient['email'])->send(
                new WorkReviewShareMail(
                    count: $reviews->count(),
                    period: $period,
                    note: $note,
                    documents: $documents,
                )
            );

            foreach ($reviews as $review) {
                WorkReviewShare::create([
                    'batch_id' => $batch,
                    'work_review_id' => $review->id,
                    'recipient_email' => $recipient['email'],
                    'recipient_org' => $recipient['org'] ?? null,
                    'note' => $note,
                    'sent_by' => $actor->id,
                    'sent_at' => $sentAt,
                ]);
            }
        }

        // 인적사항이 담긴 문서가 밖으로 나갔다 — 근로자별로 남긴다(§7-6).
        foreach ($reviews as $review) {
            $review->worker?->recordAccessBy($actor, 'work-review-share');
        }

        activity('work-review-share')
            ->causedBy($actor)
            ->withProperties([
                'batch' => $batch,
                'review_ids' => $reviews->pluck('id')->all(),
                'recipients' => array_column($recipients, 'email'),
                'count' => $reviews->count(),
            ])
            ->log('근무상태 점검표 관계기관 제출');

        return [
            'batch' => $batch,
            'reviews' => $reviews->count(),
            'recipients' => count($recipients),
        ];
    }

    /**
     * 첨부 파일 이름 — 사람 이름을 쓰지 않는다(§7-3).
     *
     * 파일명은 메일 목록·미리보기에 그대로 뜬다. 내려받아 여는 순간에야
     * 인적사항이 보이는 것과, 목록에 이름이 박히는 것은 다른 문제다.
     */
    private function attachmentName(WorkReview $review): string
    {
        $date = $review->reviewed_at?->timezone(config('ndn.timezone'))->format('Ymd') ?? 'nodate';

        return "근무상태점검표_{$date}_no{$review->id}.pdf";
    }

    /** @param  Collection<int, WorkReview>  $reviews */
    private function period(Collection $reviews): string
    {
        $tz = config('ndn.timezone');
        $dates = $reviews->map(fn (WorkReview $r) => $r->reviewed_at?->timezone($tz)->format('Y-m-d'))
            ->filter()->values();

        if ($dates->isEmpty()) {
            return '—';
        }

        return $dates->first() === $dates->last()
            ? (string) $dates->first()
            : $dates->first().' ~ '.$dates->last();
    }

    private function assertNoPersonalData(string $text): void
    {
        foreach (self::PII_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                throw ValidationException::withMessages([
                    'note' => ['안내 문구에 개인정보(전화·이메일·여권번호 등)를 넣을 수 없습니다 (§7-3). 인적사항은 첨부 문서로만 나갑니다.'],
                ]);
            }
        }
    }
}
