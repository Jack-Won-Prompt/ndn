<?php

declare(strict_types=1);

namespace App\Domains\Monitoring\Models;

use App\Domains\Recruitment\Models\Worker;
use Database\Factories\LifeChecklistCheckFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 근로자가 체크리스트 한 항목을 확인했다는 기록.
 *
 * 체크를 풀면 행을 지운다 — '확인하지 않음' 을 따로 저장하지 않는다.
 * 행이 없으면 아직 확인하지 않은 것이다.
 */
class LifeChecklistCheck extends Model
{
    /** @use HasFactory<LifeChecklistCheckFactory> */
    use HasFactory;

    protected $fillable = ['worker_id', 'life_checklist_item_id', 'checked_at'];

    protected function casts(): array
    {
        return ['checked_at' => 'datetime'];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** @return BelongsTo<LifeChecklistItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(LifeChecklistItem::class, 'life_checklist_item_id');
    }
}
