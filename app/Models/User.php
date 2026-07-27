<?php

declare(strict_types=1);

namespace App\Models;

use App\Domains\Demand\Models\City;
use App\Domains\Demand\Models\Farm;
use App\Shared\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'city_id',
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

    /** 시청 담당자의 소속 지자체 (city_officer 스코프 기준). */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** 농가 사용자가 소유한 농가들 (farm_owner 스코프 기준). */
    public function farms(): HasMany
    {
        return $this->hasMany(Farm::class, 'owner_user_id');
    }

    /**
     * 이 사용자가 특정 역할인지 확인한다. 문자열 대신 UserRole Enum 을 받는다.
     */
    public function isRole(UserRole $role): bool
    {
        return $this->hasRole($role->value);
    }

    /** 관리자 앱(포털) 로그인이 가능한 역할인지. 근로자는 별도 앱을 쓴다. */
    public function canUsePortalApp(): bool
    {
        return $this->hasAnyRole(array_map(
            fn (UserRole $r) => $r->value,
            UserRole::portalAppRoles(),
        ));
    }

    /** 관리자 앱에서 쓸 주 역할 (여러 역할이면 권한이 가장 넓은 것). */
    public function primaryPortalRole(): ?UserRole
    {
        foreach (UserRole::portalAppRoles() as $role) {
            if ($this->hasRole($role->value)) {
                return $role;
            }
        }

        return null;
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
