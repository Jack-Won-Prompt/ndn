<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Models;

use App\Domains\Recruitment\Models\Worker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 필수 문서 동의 기록 — 누가, 어느 문서의 몇 번째 버전에, 언제 동의했는지.
 *
 * 분쟁 시 증빙이 되므로 갱신하지 않고 버전별로 행을 쌓는다(§7-4 와 같은 원칙).
 */
class DocumentConsent extends Model
{
    protected $fillable = ['worker_id', 'required_document_id', 'version', 'agreed_at'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'agreed_at' => 'datetime'];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** @return BelongsTo<RequiredDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(RequiredDocument::class, 'required_document_id');
    }
}
