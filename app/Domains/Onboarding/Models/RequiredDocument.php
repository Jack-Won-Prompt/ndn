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
use Illuminate\Support\Facades\Storage;

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

    /** 원본 파일이 놓이는 곳 (public/ 아님 — 인증 라우트로만 내보낸다) */
    public const DIR = 'worker-documents';

    protected $fillable = ['code', 'translations', 'file', 'version', 'sort_order', 'required', 'active'];

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

    /**
     * 저장 디스크 — 올리기·내려받기·존재 확인이 모두 이걸 쓴다.
     *
     * 'local' 이 아니다. 그쪽 루트는 storage/app/private 인데 이 서식들은
     * storage/app/worker-documents 에 있다. 전용 디스크로 그 자리를 가리킨다.
     */
    public const DISK = 'worker-documents';

    /**
     * 내려받을 원본이 붙어 있는가.
     *
     * 파일시스템 경로를 직접 조립하지 않고 디스크로 확인한다. 올리기·내려받기가
     * 디스크를 쓰는데 확인만 따로 놀면 둘이 갈라진다.
     */
    public function hasFile(): bool
    {
        return filled($this->file) && Storage::disk(self::DISK)->exists($this->file);
    }

    /**
     * 내려받을 때 보일 파일명 — 근로자 언어로 짓는다.
     *
     * 방글라데시 근로자에게 한국어 파일명을 주면 무슨 파일인지 알 수 없다.
     * 제목은 이미 언어별로 들고 있으므로 그대로 쓰고 확장자만 원본에서 가져온다.
     * 제목이 비어 있으면 code 로 떨어진다(파일명이 비는 것보다 낫다).
     */
    public function downloadName(string $locale): string
    {
        $title = trim($this->title($locale)) ?: $this->code;

        // 파일명에 쓸 수 없는 문자만 걷어낸다. 언어 문자는 건드리지 않는다.
        $title = preg_replace('/[\/\\\\:*?"<>|]+/u', ' ', $title);
        $title = trim(preg_replace('/\s+/u', ' ', $title));

        $ext = pathinfo((string) $this->file, PATHINFO_EXTENSION);

        return $ext !== '' ? $title.'.'.$ext : $title;
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
