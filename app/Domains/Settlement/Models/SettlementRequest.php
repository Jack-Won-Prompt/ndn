<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Models;

use App\Domains\Settlement\Enums\SettlementType;
use App\Domains\Settlement\Models\Scopes\PartnerAgencyScope;
use Database\Factories\SettlementRequestFactory;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 정착 서비스 신청 (CLAUDE.md §5).
 *
 * @property SettlementType $type
 */
#[ScopedBy(PartnerAgencyScope::class)]
class SettlementRequest extends Model
{
    /** @use HasFactory<SettlementRequestFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'worker_id',
        'type',
        'assigned_agency_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => SettlementType::class,
        ];
    }
}
