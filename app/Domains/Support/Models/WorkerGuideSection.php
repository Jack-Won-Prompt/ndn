<?php

declare(strict_types=1);

namespace App\Domains\Support\Models;

use Database\Factories\WorkerGuideSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 안내 자료 안의 한 덩어리.
 *
 * type 이 payload 의 해석을 정한다. 앱은 type 별로 위젯을 골라 그린다.
 */
class WorkerGuideSection extends Model
{
    /** @use HasFactory<WorkerGuideSectionFactory> */
    use HasFactory;

    /** 앱이 그릴 수 있는 섹션 유형 (시더·테스트가 이 목록을 벗어나지 않게 한다). */
    public const TYPES = ['text', 'list', 'table', 'qa', 'contacts', 'steps'];

    protected $fillable = ['worker_guide_id', 'type', 'position', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<WorkerGuide, $this> */
    public function guide(): BelongsTo
    {
        return $this->belongsTo(WorkerGuide::class, 'worker_guide_id');
    }
}
