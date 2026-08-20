<?php

declare(strict_types=1);

namespace App\Domains\Arrival\Models;

use App\Domains\Arrival\Enums\ArrivalDocument;
use App\Domains\Arrival\Enums\ArrivalStatus;
use App\Domains\Matching\Models\Placement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 입국·이송 기록 (업무흐름 §5).
 *
 * 주의(§7-2): 위치 컬럼을 추가하지 말 것. 진행 증빙은 시각·담당자로만 남긴다.
 *
 * @property ArrivalStatus $status
 */
class ArrivalRecord extends Model
{
    // 배정 확정 때 만들어지므로 배정과 같은 운명을 따른다 —
    // 농가가 지워지면 배정과 함께 접힌다.
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'placement_id',
        'status',
        'flight_no',
        'airport',
        'scheduled_arrival_at',
        'pickup_user_id',
        'vehicle_no',
        'arrived_at',
        'picked_up_at',
        'handed_over_at',
        'documents',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArrivalStatus::class,
            'scheduled_arrival_at' => 'datetime',
            'arrived_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'handed_over_at' => 'datetime',
            'documents' => 'array',
        ];
    }

    /** @return BelongsTo<Placement, $this> */
    public function placement(): BelongsTo
    {
        return $this->belongsTo(Placement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pickupUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pickup_user_id');
    }

    /** 서류 체크리스트 — 누락 키는 미확인(false)으로 채워 항상 전체 키를 돌려준다. */
    public function checklist(): array
    {
        $saved = $this->documents ?? [];

        return array_map(
            fn (string $key) => (bool) ($saved[$key] ?? false),
            array_combine(ArrivalDocument::keys(), ArrivalDocument::keys()),
        );
    }

    /** 필수 서류를 모두 확인했는지 — 입국 단계 진행의 전제 조건. */
    public function hasRequiredDocuments(): bool
    {
        $checklist = $this->checklist();

        foreach (ArrivalDocument::cases() as $document) {
            if ($document->isRequired() && ! ($checklist[$document->value] ?? false)) {
                return false;
            }
        }

        return true;
    }

    /** 아직 확인하지 않은 필수 서류 라벨 목록 (안내 메시지용) */
    public function missingRequiredDocuments(): array
    {
        $checklist = $this->checklist();
        $missing = [];

        foreach (ArrivalDocument::cases() as $document) {
            if ($document->isRequired() && ! ($checklist[$document->value] ?? false)) {
                $missing[] = $document->label();
            }
        }

        return $missing;
    }
}
