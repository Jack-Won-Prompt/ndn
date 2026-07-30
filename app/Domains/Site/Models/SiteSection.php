<?php

declare(strict_types=1);

namespace App\Domains\Site\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 페이지 안의 한 덩어리.
 *
 * type 이 payload 의 해석을 정한다. 앱은 type 별로 위젯을 골라 그린다.
 */
class SiteSection extends Model
{
    use HasFactory;

    protected $fillable = ['site_page_id', 'type', 'position', 'payload'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<SitePage, $this> */
    public function page(): BelongsTo
    {
        return $this->belongsTo(SitePage::class, 'site_page_id');
    }
}
