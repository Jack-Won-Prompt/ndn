<?php

declare(strict_types=1);

namespace App\Shared\Notifications\Actions;

use App\Shared\Notifications\Models\DeviceToken;
use Illuminate\Database\Eloquent\Model;

/**
 * 앱이 받은 FCM 등록 토큰을 이 사용자 것으로 저장한다.
 *
 * 토큰이 유일 키라 **소유자를 갈아끼우는 것**이 핵심이다. 한 기기를 여러 사람이
 * 번갈아 쓰는 상황(관리자 공용 태블릿, 기기 양도)에서 이전 사용자 행이 남으면
 * 그 사람의 알림이 새 사용자 화면에 뜬다.
 */
class RegisterDeviceTokenAction
{
    public function execute(
        Model $owner,
        string $token,
        string $locale = 'ko',
        ?string $appVersion = null,
        string $platform = 'android',
    ): DeviceToken {
        return DeviceToken::updateOrCreate(
            ['token' => $token],
            [
                'tokenable_type' => $owner->getMorphClass(),
                'tokenable_id' => $owner->getKey(),
                'platform' => $platform,
                'locale' => $locale,
                'app_version' => $appVersion,
                'last_used_at' => now(),
            ],
        );
    }
}
