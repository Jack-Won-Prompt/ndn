<?php

declare(strict_types=1);

namespace App\Domains\Support\Actions;

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\Notice;
use App\Domains\Support\Notifications\NoticeNotification;
use App\Models\User;
use App\Shared\Translation\GoogleTranslator;
use Illuminate\Support\Collection;
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

    /**
     * @param  list<int>  $workerIds  '근로자 선택' 일 때 고른 사람들
     *
     * @throws ValidationException 개인정보가 섞였거나 대상이 비었을 때
     */
    public function execute(
        string $title,
        string $body,
        string $target,
        ?string $targetValue,
        ?int $createdBy,
        array $workerIds = [],
    ): Notice {
        $this->assertNoPersonalData($title.' '.$body);

        $workers = $this->targetWorkers($target, $targetValue, $workerIds)->get();

        // 담당자 앱을 쓰는 사람들 — '전체' 일 때만 함께 받는다.
        $users = $target === Notice::TARGET_EVERYONE ? $this->targetUsers() : collect();

        if ($workers->isEmpty() && $users->isEmpty()) {
            throw ValidationException::withMessages([
                'target' => ['받을 사람이 없습니다. 대상을 다시 고르세요.'],
            ]);
        }

        $notice = Notice::create([
            'title' => $title,
            'body' => $body,
            'target' => $target,
            'target_value' => $targetValue,
            'created_by' => $createdBy,
            'recipients_count' => $workers->count() + $users->count(),
        ]);

        // 골라 보낸 공지만 수신자를 남긴다 — 이유는 마이그레이션 주석에 적어 뒀다.
        if ($target === Notice::TARGET_SELECTED) {
            $notice->recipients()->sync($workers->pluck('id')->all());
        }

        // 언어별로 묶어 번역 호출을 최소화한다(§6).
        foreach ($workers->groupBy(fn (Worker $w) => $w->locale ?: 'ko') as $locale => $group) {
            $tTitle = $this->translate($title, (string) $locale);
            $tBody = $this->translate($body, (string) $locale);

            Notification::send($group, new NoticeNotification($notice->id, $tTitle, $tBody));
        }

        // 담당자는 한국어 원문 그대로 받는다. 번역해 보내면 원문과 달라져
        // "무슨 공지를 보냈나" 를 되짚을 때 헷갈린다.
        if ($users->isNotEmpty()) {
            Notification::send($users, new NoticeNotification($notice->id, $title, $body));
        }

        return $notice;
    }

    /**
     * 대상 근로자 쿼리.
     *
     * '선택' 을 뺀 나머지는 **재직 중인 사람만** 본다. 귀국·이탈한 사람에게
     * 공지가 가면 안 된다. 상태별은 담당자가 일부러 고른 것이라 그대로 따른다.
     *
     * @param  list<int>  $workerIds
     */
    private function targetWorkers(string $target, ?string $targetValue, array $workerIds = [])
    {
        $active = WorkerStatus::Active->value;

        return match ($target) {
            Notice::TARGET_SELECTED => Worker::whereIn('id', $workerIds !== [] ? $workerIds : [0]),
            Notice::TARGET_NATIONALITY => Worker::where('status', $active)->where('nationality', $targetValue),
            Notice::TARGET_STATUS => Worker::where('status', $targetValue ?: $active),
            // everyone·all 모두 근로자 쪽은 재직자 전체다.
            default => Worker::where('status', $active),
        };
    }

    /**
     * 담당자 앱 사용자.
     *
     * **기기를 등록한 사람만** 고른다. 앱을 안 쓰는 담당자까지 세면 발송 건수가
     * 실제로 받은 사람보다 커져 이력이 거짓말을 한다.
     *
     * @return Collection<int, User>
     */
    private function targetUsers()
    {
        return User::query()
            ->whereHas('deviceTokens')
            ->get();
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
