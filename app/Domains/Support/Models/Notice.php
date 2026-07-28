<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 근로자 공지사항 (관리자 작성 → FCM 푸시 + 인앱). title/body 는 한국어 원문.
 */
class Notice extends Model
{
    public const TARGET_ALL = 'all';

    public const TARGET_NATIONALITY = 'nationality';

    public const TARGET_STATUS = 'status';

    protected $fillable = [
        'title', 'body', 'target', 'target_value', 'created_by', 'recipients_count',
    ];

    public function targetLabel(): string
    {
        return match ($this->target) {
            self::TARGET_NATIONALITY => '국적: '.$this->target_value,
            self::TARGET_STATUS => '상태: '.$this->target_value,
            default => '전체 근로자',
        };
    }
}
