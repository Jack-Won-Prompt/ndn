<?php

declare(strict_types=1);

namespace App\Mail;

use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * 근무상태 종합 점검표 — 관계기관 제출 메일.
 *
 * **본문에는 개인정보를 넣지 않는다(§7-3).** 근로자 이름·여권번호·연락처는 물론
 * 농가명도 싣지 않고, 건수와 점검일 범위만 적는다. 첨부 파일 이름도 마찬가지라
 * 사람 이름 대신 점검표 번호를 쓴다 — 파일명은 메일 목록에 그대로 노출된다.
 *
 * 제출 자료인 PDF 자체에는 여권번호·생년월일이 들어간다. 그게 이 서식의 목적이고
 * (관공서 제출 서식), 그래서 발송 이력을 WorkReviewShare 에 남긴다.
 */
class WorkReviewShareMail extends Mailable implements PersonalDataFreeChannel
{
    use Queueable, SerializesModels;

    /**
     * @param  int  $count  첨부한 점검표 건수
     * @param  string  $period  점검일 범위 (예: 2026-07-01 ~ 2026-08-10)
     * @param  string|null  $note  담당자가 적은 안내 문구 — 개인정보가 섞이지 않게 Action 이 걸러 낸다
     * @param  array<int, array{name: string, bytes: string}>  $documents
     */
    public function __construct(
        public readonly int $count,
        public readonly string $period,
        public readonly ?string $note,
        public readonly array $documents = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectLine());
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.work-review-share', with: [
            'count' => $this->count,
            'period' => $this->period,
            'note' => $this->note,
        ]);
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        return array_map(
            fn (array $doc) => Attachment::fromData(
                fn () => $doc['bytes'],
                $doc['name'],
            )->withMime('application/pdf'),
            $this->documents,
        );
    }

    /** @return array<int, string> */
    public function outboundStrings(): array
    {
        return array_filter([
            $this->subjectLine(),
            $this->period,
            (string) $this->note,
            ...array_column($this->documents, 'name'),
        ]);
    }

    private function subjectLine(): string
    {
        return "[NDN] 외국인근로자 근무상태 종합 점검표 {$this->count}건 제출";
    }
}
