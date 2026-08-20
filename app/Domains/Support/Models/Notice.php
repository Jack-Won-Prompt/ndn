<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use App\Domains\Recruitment\Enums\Nationality;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 공지사항 (관리자 작성 → FCM 푸시 + 인앱). title/body 는 한국어 원문.
 *
 * 대상은 다섯 가지다. `all`(근로자 전체)이 원래 있던 값이라 그대로 두고 —
 * 이름을 바꾸면 지난 이력의 라벨이 전부 어긋난다 — 넓은 쪽에 `everyone` 을,
 * 좁은 쪽에 `selected` 를 새로 뒀다.
 */
class Notice extends Model
{
    /** 앱을 쓰는 모두 — 근로자 + 담당자(관리자 앱) */
    public const TARGET_EVERYONE = 'everyone';

    /** 재직 중인 근로자 전체 */
    public const TARGET_ALL = 'all';

    /** 골라 보낸 근로자 (notice_recipients 에 남는다) */
    public const TARGET_SELECTED = 'selected';

    public const TARGET_NATIONALITY = 'nationality';

    public const TARGET_STATUS = 'status';

    protected $fillable = [
        'title', 'body', 'target', 'target_value', 'created_by', 'recipients_count',
    ];

    /**
     * 골라 보낸 공지의 수신자. 범위로 보낸 공지에는 비어 있다 — 이유는
     * notice_recipients 마이그레이션 주석에 적어 뒀다.
     *
     * @return BelongsToMany<Worker, $this>
     */
    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Worker::class, 'notice_recipients')->withTimestamps();
    }

    public function targetLabel(): string
    {
        return match ($this->target) {
            self::TARGET_EVERYONE => '전체 (근로자·담당자)',
            self::TARGET_SELECTED => '선택한 근로자',
            self::TARGET_NATIONALITY => '국적: '.Nationality::tryLabel($this->target_value),
            self::TARGET_STATUS => '상태: '.(
                WorkerStatus::tryFrom((string) $this->target_value)?->label() ?? $this->target_value
            ),
            default => '근로자 전체',
        };
    }

    /**
     * 관리자 화면의 대상 선택지.
     *
     * 넓은 것부터 좁은 것 순으로 둔다. 실수로 전체 발송을 누르는 일이 가장
     * 비싸므로, 맨 위에 두고 화면에서 한 번 더 확인을 받는다.
     *
     * @return array<string, string>
     */
    public static function targetOptions(): array
    {
        return [
            self::TARGET_EVERYONE => '전체 (근로자 + 담당자 앱)',
            self::TARGET_ALL => '근로자 전체',
            self::TARGET_NATIONALITY => '국적별',
            self::TARGET_STATUS => '상태별',
            self::TARGET_SELECTED => '근로자 선택',
        ];
    }
}
