<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Models;

use App\Domains\Matching\Enums\PlacementStatus;
use App\Domains\Matching\Models\Placement;
use App\Domains\Onboarding\Models\ConsentRecord;
use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Shared\Concerns\LogsPersonalDataAccess;
use App\Shared\Concerns\MasksSensitiveData;
use App\Shared\Enums\ConsentPurpose;
use App\Shared\Enums\Gender;
use App\Shared\Support\BlindIndex;
use Carbon\Carbon;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

/**
 * 근로자 (CLAUDE.md §5, §7).
 *
 * 민감 필드는 encrypted cast 로 자동 암복호화된다(§7-1). 여권번호는 검색을 위해
 * 저장 시 blind index(passport_no_bidx)를 자동으로 함께 갱신한다.
 * 로그·배열 변환 시 민감 필드는 MasksSensitiveData 로 마스킹된다.
 *
 * @property string|null $passport_no 평문(복호화됨). DB 에는 암호문으로 저장.
 */
class Worker extends Model implements AuthenticatableContract
{
    // 근로자 앱은 Sanctum 토큰으로 인증하므로 Authenticatable 을 구현한다(비밀번호 미사용).
    use Authenticatable, HasApiTokens, HasFactory, LogsPersonalDataAccess, MasksSensitiveData, SoftDeletes;

    /**
     * 로그·toArray 에서 가릴 민감 속성 (MasksSensitiveData).
     *
     * @var list<string>
     */
    protected array $sensitive = ['passport_no', 'birth_date', 'phone_home_country', 'email'];

    protected $fillable = [
        'name',
        'email',
        'password',
        'nationality',
        'gender',
        'locale',
        'status',
        'approved_at',
        'approved_by',
        'passport_no',
        'birth_date',
        'phone_home_country',
    ];

    /**
     * 직렬화에서 아예 제외할 속성.
     *
     * @var list<string>
     */
    protected $hidden = ['password'];

    /**
     * blind index 컬럼은 애플리케이션이 파생해 채우므로 직접 대입 금지.
     *
     * @var list<string>
     */
    protected $guarded = ['passport_no_bidx'];

    protected function casts(): array
    {
        return [
            // Laravel 내장 암호화 cast — 저장 시 암호화, 조회 시 복호화 (§7-1)
            'passport_no' => 'encrypted',
            'birth_date' => 'encrypted',
            'phone_home_country' => 'encrypted',
            // 로그인 비밀번호 — 대입 시 자동 단방향 해시 (§9)
            'password' => 'hashed',
            // 계정 상태 (가입 승인제) — 문자열 리터럴 비교 금지, Enum 사용
            'status' => WorkerStatus::class,
            // 매칭 조건 대조용 (demand_requests.gender 와 대응)
            'gender' => Gender::class,
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // 여권번호가 바뀔 때마다 blind index 를 재계산해 함께 저장한다.
        static::saving(function (Worker $worker) {
            if ($worker->isDirty('passport_no')) {
                $plain = $worker->passport_no; // cast 로 복호화된 평문
                $worker->attributes['passport_no_bidx'] =
                    filled($plain) ? BlindIndex::hash((string) $plain) : null;
            }
        });
    }

    /**
     * 여권번호(평문)로 근로자를 찾는다. 암호문은 매번 달라 직접 WHERE 가 불가하므로
     * blind index 로 조회한다 (§7-1).
     *
     * @param  Builder<Worker>  $query
     */
    public function scopeWherePassport(Builder $query, string $passportNo): void
    {
        $query->where('passport_no_bidx', BlindIndex::hash($passportNo));
    }

    /** @return HasMany<ConsentRecord, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(ConsentRecord::class);
    }

    /**
     * 농가 배정 이력. 관리자 앱의 역할별 스코프(PortalScope)가 이 관계를 탄다.
     *
     * @return HasMany<Placement, $this>
     */
    public function placements(): HasMany
    {
        return $this->hasMany(Placement::class);
    }

    /** 현재 유효한 배정 (확정 건 중 최신). */
    public function currentPlacement(): ?Placement
    {
        return $this->placements()
            ->where('status', PlacementStatus::Confirmed->value)
            ->latest('id')
            ->first();
    }

    /**
     * 아직 어느 농가에도 배정되지 않은 근로자 (취소된 배정은 배정으로 치지 않는다).
     * 매칭 후보 조회의 기본 조건이다.
     *
     * @param  Builder<Worker>  $query
     */
    public function scopeUnassigned(Builder $query): void
    {
        $query->whereDoesntHave(
            'placements',
            fn (Builder $p) => $p->whereIn('status', [
                PlacementStatus::Proposed->value,
                PlacementStatus::Confirmed->value,
            ]),
        );
    }

    /**
     * 만 나이. birth_date 는 encrypted 라 SQL 로 계산할 수 없어 PHP 에서 구한다
     * (§7-1). 매칭 후보를 국적·미배정으로 좁힌 뒤 이 값으로 거른다.
     */
    public function age(): ?int
    {
        $birth = $this->birth_date;

        if (blank($birth)) {
            return null;
        }

        try {
            return (int) Carbon::parse((string) $birth)->age;
        } catch (\Throwable) {
            // 형식이 깨진 값이 있어도 매칭 전체가 죽지 않게 한다.
            return null;
        }
    }

    /**
     * 특정 목적(및 선택적으로 기관 유형)에 대한 활성(미철회) 동의가 있는지 (CLAUDE.md §7-4).
     * 대리점·제휴사 제공 쿼리는 이 결과를 Policy 에서 확인해야 한다.
     */
    public function hasActiveConsent(ConsentPurpose $purpose, ?string $agencyType = null): bool
    {
        return $this->consents()
            ->active()
            ->where('purpose', $purpose->value)
            ->when($agencyType !== null, fn ($q) => $q->where('agency_type', $agencyType))
            ->exists();
    }
}
