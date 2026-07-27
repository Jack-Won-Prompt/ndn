<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 접속·페이지 접근 로그 (RecordAccessLog 미들웨어가 기록).
 */
class AccessLog extends Model
{
    /** 갱신 시각은 쓰지 않는다(생성만). */
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'actor', 'actor_email', 'method', 'path', 'route_name',
        'status', 'ip', 'user_agent', 'referer', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
