<?php

declare(strict_types=1);

namespace App\Shared\Notifications\Contracts;

/**
 * 개인정보가 실려서는 안 되는 외부 알림 채널 마커 인터페이스 (CLAUDE.md §7-3, §8).
 *
 * 알림톡·SMS·이메일 등 로그인 없이 도달하는 외부 채널로 나가는 Notification 은
 * 이 인터페이스를 구현해야 한다. 본문에는 이름·여권번호·전화번호·주소를 넣지 않으며
 * 허용되는 것은 "건수 + 로그인 후 접근하는 링크" 뿐이다.
 *
 * PersonalDataInNotificationTest 가 이 인터페이스를 구현한 모든 Notification 의
 * 렌더링 결과에 개인정보 패턴이 없는지 강제 검사한다.
 */
interface PersonalDataFreeChannel
{
    /**
     * 검사 대상이 되는, 외부로 전송되는 문자열들을 반환한다.
     * (제목·본문·버튼 라벨 등 사람이 읽는 모든 텍스트)
     *
     * @return array<int, string>
     */
    public function outboundStrings(): array;
}
