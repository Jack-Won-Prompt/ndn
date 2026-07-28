<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 사용자 계정·데이터 삭제 요청 (Google Play 데이터 삭제 정책).
 * 공개 페이지에서 접수 → 관리자 확인 → 실제 계정 soft delete → 90일 후 파기(§7-7).
 */
class AccountDeletionRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'name', 'email', 'reason', 'status', 'admin_note', 'processed_by', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processed_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
