<?php

declare(strict_types=1);

namespace App\Shared\Notifications\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * 푸시 알림을 받을 기기 한 대 (FCM 등록 토큰).
 *
 * 근로자·관리자가 같은 앱을 쓰므로 소유자는 다형 관계다. 도메인 하나에 속하지
 * 않아 Shared 에 둔다.
 *
 * @property string $token
 * @property string $locale
 */
class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'tokenable_type',
        'tokenable_id',
        'token',
        'platform',
        'locale',
        'app_version',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /**
     * 이 토큰의 주인 (Worker 또는 User).
     *
     * @return MorphTo<Model, $this>
     */
    public function tokenable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * 로그에 토큰 원문을 남기지 않는다.
     *
     * 토큰 자체가 개인정보는 아니지만, 이것만 있으면 해당 기기에 푸시를 보낼 수
     * 있어 자격증명에 준해 다룬다.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = parent::toArray();

        if (isset($data['token'])) {
            $data['token'] = self::mask($this->token);
        }

        return $data;
    }

    /** 앞 8자 + 길이만 남긴다 — 같은 기기인지 대조하는 용도. */
    public static function mask(string $token): string
    {
        return mb_substr($token, 0, 8).'…('.mb_strlen($token).')';
    }
}
