<?php

declare(strict_types=1);

namespace App\Shared\Enums;

/**
 * 초대 상태 (필드에서 파생: 철회 > 수락 > 만료 > 대기).
 */
enum InvitationStatus: string
{
    case Pending = 'pending';    // 대기 (수락 전, 미만료)
    case Accepted = 'accepted';  // 수락됨 (계정 생성 완료)
    case Expired = 'expired';    // 만료
    case Revoked = 'revoked';    // 철회

    public function label(): string
    {
        return match ($this) {
            self::Pending => '대기',
            self::Accepted => '수락됨',
            self::Expired => '만료',
            self::Revoked => '철회',
        };
    }
}
