<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\Notice;
use App\Domains\Support\Notifications\NoticeNotification;
use App\Shared\Translation\GoogleTranslator;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * 근로자 공지사항 발송 (CLAUDE.md §6, §7-3, §8).
 *
 * 1) 제목·본문에 개인정보 패턴이 있으면 거부(§7-3).
 * 2) 대상(전체/국적/상태) 근로자를 언어별로 묶어 자동 번역(§6).
 * 3) NoticeNotification 으로 FCM + 인앱 발송. 발송 수를 Notice 에 기록.
 */
class SendNoticeAction
{
    /** 개인정보로 간주하는 패턴 (PersonalDataInNotificationTest 와 동일 기준) */
    private const PII_PATTERNS = [
        '/\b[A-Z][0-9]{7,8}\b/',            // 여권번호
        '/\b01[0-9]-?[0-9]{3,4}-?[0-9]{4}\b/', // 휴대폰
        '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', // 이메일
        '/\b[0-9]{6}-?[1-4][0-9]{6}\b/',    // 주민등록번호
    ];

    public function execute(string $title, string $body, string $target, ?string $targetValue, ?int $createdBy): Notice
    {
        $this->assertNoPersonalData($title.' '.$body);

        $workers = $this->targetWorkers($target, $targetValue)->get();

        $notice = Notice::create([
            'title' => $title,
            'body' => $body,
            'target' => $target,
            'target_value' => $targetValue,
            'created_by' => $createdBy,
            'recipients_count' => $workers->count(),
        ]);

        // 언어별로 묶어 번역 호출을 최소화한다(§6).
        foreach ($workers->groupBy(fn (Worker $w) => $w->locale ?: 'ko') as $locale => $group) {
            $tTitle = $this->translate($title, (string) $locale);
            $tBody = $this->translate($body, (string) $locale);

            Notification::send($group, new NoticeNotification($notice->id, $tTitle, $tBody));
        }

        return $notice;
    }

    /** 대상 근로자 쿼리 */
    private function targetWorkers(string $target, ?string $targetValue)
    {
        $active = WorkerStatus::Active->value;

        return match ($target) {
            Notice::TARGET_NATIONALITY => Worker::where('status', $active)->where('nationality', $targetValue),
            Notice::TARGET_STATUS => Worker::where('status', $targetValue ?: $active),
            default => Worker::where('status', $active),
        };
    }

    private function translate(string $text, string $locale): string
    {
        return $locale === 'ko' ? $text : GoogleTranslator::translate($text, $locale, 'ko');
    }

    private function assertNoPersonalData(string $text): void
    {
        foreach (self::PII_PATTERNS as $pattern) {
            if (preg_match($pattern, $text)) {
                throw ValidationException::withMessages([
                    'body' => ['공지 본문에 개인정보(전화·이메일·여권번호 등)를 포함할 수 없습니다 (§7-3).'],
                ]);
            }
        }
    }
}
