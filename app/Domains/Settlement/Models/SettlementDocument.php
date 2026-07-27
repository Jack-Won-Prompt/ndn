<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 정착 서비스 처리 증빙 문서 (대리점 업로드). private 저장, 다운로드 시 대리점명 워터마크(§7-5).
 */
class SettlementDocument extends Model
{
    protected $fillable = [
        'settlement_request_id', 'uploaded_by', 'disk_path', 'original_name', 'mime', 'size',
    ];

    /** @return BelongsTo<SettlementRequest, $this> */
    public function settlementRequest(): BelongsTo
    {
        return $this->belongsTo(SettlementRequest::class);
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
