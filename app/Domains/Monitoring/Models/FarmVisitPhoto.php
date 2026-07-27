<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 농가 방문 현장 사진 (private 저장). 방문 증빙용.
 */
class FarmVisitPhoto extends Model
{
    /** 생성 시각만 관리(수정 없음). */
    public const UPDATED_AT = null;

    protected $fillable = [
        'farm_visit_id', 'path', 'original_name', 'size', 'mime', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<FarmVisit, $this> */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(FarmVisit::class, 'farm_visit_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
