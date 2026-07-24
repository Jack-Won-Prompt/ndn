<?php

declare(strict_types=1);

use App\Domains\Support\Notifications\NewNoticeNotification;
use App\Shared\Notifications\Contracts\PersonalDataFreeChannel;

/**
 * 외부 알림 본문 개인정보 가드 (CLAUDE.md §7-3, §10).
 *
 * PersonalDataFreeChannel 을 구현한 알림의 outboundStrings() 에
 * 개인정보 패턴(여권번호·전화번호·이메일·주민번호 형식)이 없는지 검사한다.
 *
 * 절대 삭제 금지 가드 테스트.
 */

/** 개인정보로 간주하는 정규식 패턴 */
function personalDataPatterns(): array
{
    return [
        'passport' => '/\b[A-Z][0-9]{7,8}\b/',            // 여권번호 (예: M1234567)
        'phone' => '/\b01[0-9]-?[0-9]{3,4}-?[0-9]{4}\b/', // 휴대폰
        'email' => '/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i',
        'rrn' => '/\b[0-9]{6}-?[1-4][0-9]{6}\b/',    // 주민등록번호 형식
    ];
}

it('근로자 알림 본문에 개인정보 패턴이 없다', function () {
    // 검사 대상: PersonalDataFreeChannel 구현 알림들.
    // 새 알림을 추가하면 여기에 등록한다.
    $notifications = [
        new NewNoticeNotification(count: 3, workerLocale: 'ko'),
        new NewNoticeNotification(count: 3, workerLocale: 'vi'),
        new NewNoticeNotification(count: 3, workerLocale: 'bn'),
    ];

    foreach ($notifications as $notification) {
        expect($notification)->toBeInstanceOf(PersonalDataFreeChannel::class);

        $text = implode(' ', $notification->outboundStrings());

        foreach (personalDataPatterns() as $label => $pattern) {
            expect(preg_match($pattern, $text))->toBe(
                0,
                "알림 본문에 {$label} 개인정보 패턴이 감지되었습니다: {$text}"
            );
        }
    }
})->group('guard');

it('개인정보가 섞이면 가드가 실제로 잡아낸다', function () {
    // 가드 자체가 동작하는지 확인하는 음성 대조군.
    $withPassport = 'M1234567 님 안녕하세요';

    $caught = false;
    foreach (personalDataPatterns() as $pattern) {
        if (preg_match($pattern, $withPassport)) {
            $caught = true;
            break;
        }
    }

    expect($caught)->toBeTrue();
})->group('guard');
