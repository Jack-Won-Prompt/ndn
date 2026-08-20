<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Recruitment\Enums\ScreeningStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Notifications\SupplementRequestedNotification;
use App\Models\User;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * 가입 서류 보완 요청 (업무흐름 §2).
 *
 * 승인 전 근로자는 앱에 로그인할 수 없다. 그래서 "다시 내 주세요" 를 전할 길이
 * 이메일밖에 없고, 그 메일에서 곧바로 이어서 낼 수 있어야 한다 — 처음부터 다시
 * 가입하게 하면 이미 쓴 것을 또 쓰게 되고, 그 과정에서 여권번호가 바뀌어 들어와
 * 중복 계정이 생긴다.
 *
 * 그래서 **기한이 있는 서명 링크**를 보낸다. 로그인 없이 열리지만 URL 이 위조되면
 * 열리지 않고, 기한이 지나면 다시 요청해야 한다.
 */
class RequestSupplementAction
{
    /** 링크 유효기간. 우편으로 서류를 준비하는 시간을 감안한 길이다. */
    public const EXPIRES_DAYS = 14;

    /**
     * @param  list<string>  $items  부족한 항목 라벨 (예: '여권 사본')
     *
     * @throws RuntimeException 항목이 비었거나 이미 처리된 신청일 때
     */
    public function execute(Worker $worker, array $items, ?string $note, User $admin): Worker
    {
        $items = array_values(array_filter(array_map('trim', $items)));

        if ($items === []) {
            throw new RuntimeException('무엇을 보완해야 하는지 한 가지 이상 골라 주세요.');
        }

        if (! $worker->status->isPending()) {
            throw new RuntimeException(
                "이미 처리된 신청입니다 (현재 {$worker->status->label()})."
            );
        }

        if (blank($worker->email)) {
            throw new RuntimeException('이 신청에는 이메일이 없어 보완 요청을 보낼 수 없습니다.');
        }

        $worker->forceFill([
            'screening_status' => ScreeningStatus::SupplementRequested,
            'screening_note' => $note,
            'screened_at' => now(),
            'screened_by' => $admin->id,
            'supplement_items' => $items,
            'supplement_requested_at' => now(),
        ])->save();

        activity('worker-account')
            ->performedOn($worker)
            ->causedBy($admin)
            // 항목 라벨은 서식 이름이라 개인정보가 아니다. 무엇을 요구했는지 남겨야
            // 나중에 "왜 다시 내라고 했나" 를 되짚을 수 있다.
            ->withProperties(['items' => $items, 'note' => $note])
            ->log('가입 서류 보완 요청');

        $worker->notify(new SupplementRequestedNotification(
            supplementUrl: self::url($worker),
            count: count($items),
            expiresInDays: self::EXPIRES_DAYS,
            workerLocale: $worker->locale ?: 'ko',
        ));

        return $worker;
    }

    /** 기한이 있는 서명 링크. 로그인 없이 열리되 위조·만료는 막힌다. */
    public static function url(Worker $worker): string
    {
        return URL::temporarySignedRoute(
            'site.apply.supplement',
            now()->addDays(self::EXPIRES_DAYS),
            ['worker' => $worker->id],
        );
    }
}
