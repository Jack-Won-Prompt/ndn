<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Actions;

use App\Domains\Monitoring\Enums\WorkReviewSection;
use App\Domains\Monitoring\Models\WorkReview;
use App\Domains\Monitoring\Models\WorkReviewAnswer;
use App\Domains\Monitoring\Models\WorkReviewItem;
use App\Shared\Support\LocalTime;
use App\Shared\Support\SignatureImage;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfInstance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

/**
 * 근무상태 종합 점검표 PDF (원본 서식 그대로).
 *
 * 원본 각주: "본 점검표는 … 관할 지자체 및 관계기관의 요청 시 제출할 수 있습니다."
 * 제출이 이 서식의 존재 이유라 화면으로만 두면 반쪽이다.
 *
 * **개인정보를 담는다.** 관공서 제출 서식이라 원본 §3 에 여권번호·생년월일 칸이
 * 있고 비워 두면 제출이 되지 않는다. 대신
 *   - PDF 를 파일로 저장하지 않는다. 요청할 때마다 만들어 바로 내보낸다.
 *     저장해 두면 암호화 필드를 평문으로 복사해 쌓아 두는 셈이 된다(§7-1).
 *   - 내려받을 때 열람 기록을 남긴다(§7-6). 기록은 컨트롤러가 남긴다.
 *   - NDN 관리자만 받을 수 있다(라우트 미들웨어).
 */
class GenerateWorkReviewPdfAction
{
    /**
     * 화면·PDF 가 함께 쓰는 렌더 데이터.
     *
     * @return array<string, mixed>
     */
    public function data(WorkReview $review): array
    {
        $review->loadMissing(['worker', 'farm.city', 'inspector', 'answers.item']);

        $worker = $review->worker;
        $placement = $worker?->currentPlacement();
        $arrival = $placement?->arrival;

        return [
            'review' => $review,
            'generated_at' => LocalTime::format(now()),

            // §2 농가 및 사업장 정보 — 점검표에 복사해 두지 않고 관계에서 잇는다.
            'farm' => $review->farm,

            // §3 외국인근로자 기본정보 — 암호화 필드를 여기서 푼다(§7-1).
            // 우리가 들고 있지 않은 칸(영문 성명·비자번호 등)은 손으로 적도록 비워 둔다.
            'worker' => $worker,
            // birth_date 는 encrypted cast 라 Carbon 이 아니라 문자열로 온다.
            // 뷰에서 날짜처럼 다루면 깨지므로 여기서 표시할 글자로 만든다.
            'birth_date' => self::asDateText($worker?->birth_date),
            'age' => $worker?->age(),
            'passport_no' => $worker?->passport_no,
            'phone_home' => $worker?->phone_home_country,
            'entered_on' => $arrival?->arrived_at ?? $arrival?->scheduled_arrival_at,
            'contract_from' => $placement?->start_date,
            'contract_to' => $placement?->end_date,

            // §4~§7 점검 항목 — 원본과 같은 영역 순서로 묶는다.
            'sections' => $this->sections($review),

            // §12 서명 — dompdf 는 인증 라우트를 못 부르므로 이미지를 직접 심는다.
            'signatures' => $this->signatures($review),
        ];
    }

    public function pdf(WorkReview $review): PdfInstance
    {
        // dompdf 폰트 메트릭 캐시 디렉터리 (쓰기 가능해야 함)
        $fontCache = storage_path('fonts');
        if (! is_dir($fontCache)) {
            mkdir($fontCache, 0775, true);
        }

        return Pdf::loadView('reports.work-review', $this->data($review))
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

    /**
     * 생년월일 표기.
     *
     * 형식이 깨진 값이 섞여 있어도 제출본 생성 전체가 죽지 않게 한다
     * (Worker::age() 와 같은 태도). 못 읽으면 저장된 값을 그대로 보여 준다.
     */
    private static function asDateText(mixed $value): string
    {
        if (blank($value)) {
            return '';
        }

        try {
            return CarbonImmutable::parse((string) $value)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    /** 내려받을 파일 이름 — 누구의 언제 점검인지 파일명만 봐도 알게. */
    public function filename(WorkReview $review): string
    {
        $date = $review->reviewed_at?->timezone(config('ndn.timezone'))->format('Ymd') ?? 'nodate';
        $name = preg_replace('/[\/\\\\:*?"<>|]+/u', '', (string) $review->worker?->name) ?: 'worker';

        return "근무상태 점검표_{$name}_{$date}.pdf";
    }

    /**
     * 영역별 항목·응답 (응답하지 않은 항목도 빈칸으로 남긴다).
     *
     * 종이 서식과 같아야 제출본으로 쓸 수 있다. 응답이 없다고 줄을 빼 버리면
     * 원본과 항목 수가 달라진다.
     *
     * @return list<array{label: string, options: array<string,string>, rows: list<array{label: string, value: ?string, bad: bool, note: ?string}>}>
     */
    private function sections(WorkReview $review): array
    {
        $byItem = $review->answers->keyBy('work_review_item_id');

        $items = WorkReviewItem::query()
            ->active()->get()
            ->groupBy(fn ($i) => $i->section->value);

        return collect(WorkReviewSection::ordered())
            ->map(fn (WorkReviewSection $s) => [
                'label' => $s->label(),
                'options' => $s->options(),
                'rows' => ($items->get($s->value) ?? collect())
                    ->map(function ($item) use ($byItem) {
                        /** @var WorkReviewAnswer|null $answer */
                        $answer = $byItem->get($item->id);
                        $value = $answer?->value;

                        return [
                            'label' => $item->label,
                            'value' => $value,
                            'bad' => $value !== null && $item->scored && $item->isBad($value),
                            'note' => $answer?->note,
                        ];
                    })->values()->all(),
            ])
            ->all();
    }

    /**
     * §12 서명 — 이미지를 data URI 로 심는다.
     *
     * private 저장이라 URL 로는 못 가져온다. dompdf 가 인증 쿠키를 들고 요청할
     * 방법도 없으므로 바이트를 직접 넣는다.
     *
     * @return list<array{label: string, name: ?string, image: ?string}>
     */
    private function signatures(WorkReview $review): array
    {
        return collect(WorkReview::SIGNATURE_ROLES)
            ->map(function (array $def, string $role) use ($review) {
                $image = null;

                if ($review->hasSignature($role)) {
                    $path = $review->signaturePath($role);
                    $bytes = Storage::disk(SignatureImage::DISK)->get($path);
                    if ($bytes !== null) {
                        $image = 'data:'.SignatureImage::mime($path).';base64,'.base64_encode($bytes);
                    }
                }

                return ['label' => $def[2], 'name' => $review->{$def[0]}, 'image' => $image];
            })
            ->values()
            ->all();
    }
}
