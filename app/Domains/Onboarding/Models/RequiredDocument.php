<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Models;

use App\Domains\Recruitment\Models\Worker;
use Database\Factories\RequiredDocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 근로자가 반드시 읽고 동의해야 하는 문서 (약관·계약서·약정서).
 *
 * 본문은 법적 효력이 있는 문안이므로 코드에 고정하지 않고 콘솔에서 입력한다.
 * 5개 언어(§6)를 translations JSON 에 담고, 근로자 locale 로 렌더한다.
 */
class RequiredDocument extends Model
{
    /** @use HasFactory<RequiredDocumentFactory> */
    use HasFactory;

    /** 지원 언어 (CLAUDE.md §6) */
    public const LOCALES = ['ko', 'bn', 'lo', 'si', 'vi', 'ne', 'ky'];

    protected $fillable = ['code', 'translations', 'version', 'sort_order', 'required', 'active'];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'version' => 'integer',
            'sort_order' => 'integer',
            'required' => 'boolean',
            'active' => 'boolean',
        ];
    }

    /** @return HasMany<DocumentConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(DocumentConsent::class);
    }

    /**
     * 근로자에게 보여줄 순서대로, 사용 중인 문서만.
     *
     * @param  Builder<RequiredDocument>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * 해당 언어의 제목. 번역이 없으면 한국어로 떨어진다 —
     * 법적 문서라 빈 화면을 보여주는 것보다 원문을 보여주는 편이 낫다.
     */
    public function title(string $locale): string
    {
        return $this->text($locale, 'title');
    }

    public function body(string $locale): string
    {
        return $this->text($locale, 'body');
    }

    /** 이 언어의 번역이 채워져 있는가 (콘솔에서 누락 표시에 쓴다). */
    public function hasTranslation(string $locale): bool
    {
        $t = $this->translations[$locale] ?? null;

        return filled($t['title'] ?? null) && filled($t['body'] ?? null);
    }

    /**
     * 아직 동의하지 않은 **필수** 문서들 (현재 버전 기준).
     *
     * 비어 있으면 앱을 계속 쓸 수 있고, 하나라도 남아 있으면 동의 화면에서 막힌다.
     *
     * @return Collection<int, self>
     */
    public static function pendingFor(Worker $worker)
    {
        return self::query()
            ->active()
            ->where('required', true)
            // 현재 버전에 대한 동의가 없는 문서만 — 버전이 오르면 다시 걸린다.
            ->whereDoesntHave('consents', fn (Builder $q) => $q
                ->where('worker_id', $worker->id)
                ->whereColumn('document_consents.version', 'required_documents.version'))
            ->get();
    }

    private function text(string $locale, string $key): string
    {
        $t = $this->translations ?? [];

        return (string) ($t[$locale][$key] ?? $t['ko'][$key] ?? '');
    }

    /**
     * 이 근로자가 **현재 버전**에 동의했는가.
     * 문안이 바뀌어 version 이 올라가면 예전 동의는 인정하지 않는다.
     */
    public function isAgreedBy(Worker $worker): bool
    {
        return $this->consents()
            ->where('worker_id', $worker->id)
            ->where('version', $this->version)
            ->exists();
    }
}
