<?php

declare(strict_types=1);

namespace App\Domains\Site\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 회사소개 한 페이지 (앱 네이티브 화면 하나에 대응).
 */
class SitePage extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'title', 'nav_label', 'lead', 'hero_image', 'position', 'in_app_nav', 'icon',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'in_app_nav' => 'boolean',
        ];
    }

    /** @return HasMany<SiteSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(SiteSection::class)->orderBy('position');
    }
}
