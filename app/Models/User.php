<?php

declare(strict_types=1);

namespace App\Models;

use App\Shared\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'assigned_agency_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * 이 사용자가 특정 역할인지 확인한다. 문자열 대신 UserRole Enum 을 받는다.
     */
    public function isRole(UserRole $role): bool
    {
        return $this->hasRole($role->value);
    }

    /**
     * 2FA 가 필수인 역할(ndn_admin, partner_agency)을 가졌는지 (CLAUDE.md §2).
     */
    public function mustUseTwoFactor(): bool
    {
        foreach (UserRole::cases() as $role) {
            if ($role->requiresTwoFactor() && $this->hasRole($role->value)) {
                return true;
            }
        }

        return false;
    }
}
