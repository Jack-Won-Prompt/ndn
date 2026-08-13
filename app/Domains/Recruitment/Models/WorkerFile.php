<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Models;

use App\Domains\Recruitment\Enums\WorkerFileType;
use App\Models\User;
use Database\Factories\WorkerFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * 근로자 개인 서류 한 건 (여권 사본·건강검진 결과 등).
 *
 * @property WorkerFileType $type
 */
class WorkerFile extends Model
{
    /** @use HasFactory<WorkerFileFactory> */
    use HasFactory;

    /** private 디스크. 서류는 인증 라우트로만 나간다(§9). */
    public const DISK = 'local';

    /** 저장 위치 — 근로자별로 묶는다. 파기할 때 통째로 지우기 쉽다. */
    public const DIR = 'worker-files';

    /** 여권 사본은 스캔본이 크다. 그래도 20MB 를 넘길 이유는 없다. */
    public const MAX_KB = 20480;

    /** 서류로 받아 줄 형식. 실행 파일·압축은 받지 않는다. */
    public const MIMES = 'pdf,jpg,jpeg,png,webp,doc,docx,hwp,hwpx';

    protected $fillable = [
        'worker_id', 'type', 'path', 'original_name', 'size', 'mime',
        'expires_on', 'note', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkerFileType::class,
            'size' => 'integer',
            'expires_on' => 'date',
        ];
    }

    /** @return BelongsTo<Worker, $this> */
    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function exists(): bool
    {
        return filled($this->path) && Storage::disk(self::DISK)->exists($this->path);
    }

    /** 유효기간이 지났는가 (비자·건강검진처럼 만료가 있는 서류). */
    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    /**
     * 곧 만료되는가 — 미리 챙기라고 알린다.
     *
     * diffInDays() 로 재지 않는다. Carbon 3 은 부호를 붙여 돌려주므로 미래
     * 날짜가 음수가 되어, 1년 뒤 서류까지 '임박' 으로 잡힌다.
     */
    public function expiresSoon(int $days = 30): bool
    {
        return $this->expires_on !== null
            && ! $this->isExpired()
            && $this->expires_on->lessThanOrEqualTo(now()->addDays($days));
    }

    /** 사람이 읽는 크기. */
    public function sizeLabel(): string
    {
        $bytes = (int) $this->size;
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / 1024 / 1024, 1).' MB';
    }
}
