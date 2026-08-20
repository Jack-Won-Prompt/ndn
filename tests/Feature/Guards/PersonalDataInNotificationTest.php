<?php

declare(strict_types=1);

use App\Domains\Recruitment\Notifications\SupplementRequestedNotification;
use App\Domains\Recruitment\Notifications\WorkerResetPasswordNotification;
use App\Domains\Settlement\Notifications\SettlementAssignedNotification;
use App\Domains\Support\Notifications\NewNoticeNotification;
use App\Domains\Support\Notifications\NoticeNotification;
use App\Domains\Support\Notifications\ServiceRequestCompletedNotification;
use App\Mail\WorkReviewShareMail;
use App\Notifications\InvitationNotification;
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
        // 조직 초대 이메일 — 역할 라벨 + 링크만, 개인정보 없음
        new InvitationNotification(acceptUrl: 'http://localhost/ndn/invite/'.str_repeat('a1', 20), roleLabel: '시청 담당자'),
        new InvitationNotification(acceptUrl: 'http://localhost/ndn/invite/'.str_repeat('b2', 20), roleLabel: '제휴 대리점'),
        // 대리점 배정 알림 — 건수 + 서비스 유형 + 로그인 안내만, 개인정보 없음
        new SettlementAssignedNotification(count: 2, typeLabel: '통신'),
        new SettlementAssignedNotification(count: 1, typeLabel: '보험'),
        // 공지사항 — 관리자 작성 텍스트(발송 전 개인정보 패턴 차단). 정상 샘플 검사
        new NoticeNotification(noticeId: 1, noticeTitle: '안전 교육 안내', noticeBody: '이번 주 안전 교육이 있습니다.'),
        // SR 적용 완료 메일 — SR 번호 + 콘솔 링크만. 자유 입력인 제목·본문은 싣지 않는다.
        new ServiceRequestCompletedNotification(serviceRequestId: 12, consoleUrl: 'http://localhost/ndn/admin'),
        // 가입 서류 보완 요청 — 부족한 항목 '개수' 와 기한부 링크만. 무엇이 부족한지는 링크를 열어야 보인다.
        new SupplementRequestedNotification(
            supplementUrl: 'http://localhost/ndn/apply/supplement/12?signature='.str_repeat('c3', 20),
            count: 2, expiresInDays: 14, workerLocale: 'ko',
        ),
        new SupplementRequestedNotification(
            supplementUrl: 'http://localhost/ndn/apply/supplement/12?signature='.str_repeat('c3', 20),
            count: 1, expiresInDays: 14, workerLocale: 'vi',
        ),
        // 근로자 비밀번호 재설정 — 링크와 유효시간뿐. 이름도 부르지 않는다.
        new WorkerResetPasswordNotification(
            resetUrl: 'http://localhost/ndn/worker/reset-password/'.str_repeat('d4', 20),
            expiresInMinutes: 60, workerLocale: 'bn',
        ),
        // 관계기관 제출 메일 — 인적사항은 첨부 PDF 안에만 있고, 본문·첨부 파일명에는 없다.
        new WorkReviewShareMail(
            count: 3,
            period: '2026-07-01 ~ 2026-08-10',
            note: '요청하신 점검 결과를 제출합니다.',
            documents: [['name' => '근무상태점검표_20260810_no12.pdf', 'bytes' => '']],
        ),
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
