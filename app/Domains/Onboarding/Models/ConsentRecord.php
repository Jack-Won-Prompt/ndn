<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Models;

use App\Domains\Recruitment\Models\Worker;
use App\Shared\Enums\ConsentPurpose;
use Database\Factories\ConsentRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 동의 이력 (CLAUDE.md §7-4).
 *
 * @property ConsentPurpose $purpose
 */
class ConsentRecord extends Model
{
    /** @use HasFactory<ConsentRecordFactory> */
    use HasFactory;

    protected $fillable = [
        'worker_id',
        'purpose',
        'agency_type',
        'agency_id',
        'item',
        'granted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'purpose' => ConsentPurpose::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * 철회되지 않은(활성) 동의만.
     *
     * @param  Builder<ConsentRecord>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }
}
