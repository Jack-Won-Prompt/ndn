<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\InvitationStatus;
use App\Shared\Enums\UserRole;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 조직 초대. token 은 해시만 저장하며 평문은 발송 링크에만 실린다.
 * 상태(status)는 저장하지 않고 revoked/accepted/expires 필드에서 파생한다.
 */
class Invitation extends Model
{
    protected $fillable = [
        'email', 'name', 'role', 'assigned_agency_id', 'token',
        'invited_by', 'expires_at', 'accepted_at', 'accepted_user_id', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** 파생 상태 (철회 > 수락 > 만료 > 대기). */
    public function status(): InvitationStatus
    {
        return match (true) {
            $this->revoked_at !== null => InvitationStatus::Revoked,
            $this->accepted_at !== null => InvitationStatus::Accepted,
            $this->expires_at !== null && $this->expires_at->isPast() => InvitationStatus::Expired,
            default => InvitationStatus::Pending,
        };
    }

    public function isPending(): bool
    {
        return $this->status() === InvitationStatus::Pending;
    }

    public function roleEnum(): UserRole
    {
        return UserRole::from($this->role);
    }

    /** 평문 토큰 → 저장용 해시. */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** @param  Builder<Invitation>  $query */
    public function scopeForToken(Builder $query, string $plain): void
    {
        $query->where('token', self::hashToken($plain));
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
