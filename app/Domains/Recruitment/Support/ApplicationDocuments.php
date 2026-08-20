<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Support;

use App\Domains\Recruitment\Enums\WorkerFileType;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Models\WorkerFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 가입 신청과 함께 받는 서류 (업무흐름 §2).
 *
 * 웹(`/apply`)과 앱(`POST /api/v1/auth/register`)이 **같은 규칙·같은 저장 경로**를
 * 쓴다. 두 입구가 어긋나면 같은 자료가 두 모양으로 쌓이고, 담당자가 콘솔에서
 * 보는 목록이 어느 쪽으로 들어왔느냐에 따라 달라진다.
 *
 * 정해 둔 것 세 가지.
 *
 *  1. **막지 않는다.** 파일이 없어도 접수된다. 현지에서 스캔본을 바로 구하지
 *     못하는 일이 많아, 막으면 신청 자체가 끊긴다. 부족하면 담당자가 보완을
 *     요청한다(RequestSupplementAction).
 *  2. **유형을 고르게 하지 않는다.** 전부 '기타'로 넣고 담당자가 콘솔에서
 *     분류한다 — 어떤 서류인지 근로자가 판단하기 어렵고, 잘못 붙은 분류는
 *     없느니만 못하다. 원본 파일명은 그대로 남으므로 무엇인지 알아볼 수 있다.
 *  3. **누가 올렸는지 남긴다.** `uploaded_by` 를 비워 두어 담당자가 올린 것과
 *     본인이 올린 것을 구분한다.
 */
class ApplicationDocuments
{
    /** 한 번에 올릴 수 있는 파일 수. */
    public const MAX_FILES = 10;

    /**
     * 준비해 오라고 안내하는 서류.
     *
     * 이것은 **안내일 뿐 검증하지 않는다.** 무엇을 준비해야 하는지 알려 주는
     * 것이 목적이고, 실제로 무엇이 왔는지는 담당자가 본다.
     *
     * 콘솔의 보완 요청 항목 후보도 이 목록에서 나온다
     * (SignupApprovalController::supplementItems).
     *
     * @return list<string>
     */
    public static function expected(?string $locale = null): array
    {
        return array_map(
            fn (string $key) => self::label($key, $locale),
            self::EXPECTED_KEYS,
        );
    }

    /** 안내하는 서류의 번역 키. */
    public const EXPECTED_KEYS = ['doc_passport', 'doc_photo', 'doc_health', 'doc_criminal'];

    /**
     * 담당자가 보완을 요청할 때 고르는 항목.
     *
     * **키로 다룬다.** 한국어 라벨을 그대로 저장하면 근로자에게 보여 줄 때
     * 기계 번역에 맡기게 되고, 서식 이름은 그쪽이 자주 틀린다. 키를 저장해
     * 두면 받는 사람 언어로 정확히 꺼낼 수 있다.
     *
     * @return list<string>
     */
    public const SUPPLEMENT_KEYS = [
        'doc_passport', 'doc_photo', 'doc_health', 'doc_criminal',
        'doc_birth_date', 'doc_phone', 'doc_passport_retake', 'doc_other',
    ];

    /**
     * 콘솔의 보완 요청 선택지 — 키 => 한국어 라벨.
     *
     * @return array<string, string>
     */
    public static function supplementOptions(): array
    {
        return collect(self::SUPPLEMENT_KEYS)
            ->mapWithKeys(fn (string $k) => [$k => self::label($k)])
            ->all();
    }

    /**
     * 저장된 항목을 사람이 읽는 글자로.
     *
     * 키가 아니면 그대로 돌려준다 — 이 기능 전에 저장된 건은 한국어 라벨이
     * 그대로 들어 있어서, 옛 자료를 열었을 때 'doc_passport' 같은 것이 보이면 안 된다.
     */
    public static function label(string $key, ?string $locale = null): string
    {
        if (! in_array($key, self::SUPPLEMENT_KEYS, true)) {
            return $key;
        }

        return trans('worker.'.$key, [], $locale ?: 'ko');
    }

    /**
     * 저장된 항목 목록을 그 사람 언어로.
     *
     * @param  list<string>  $keys
     * @return list<string>
     */
    public static function labels(array $keys, ?string $locale = null): array
    {
        return array_map(fn (string $k) => self::label($k, $locale), $keys);
    }

    /**
     * 검증 규칙. 가입(RegisterWorkerRequest)과 보완 제출이 함께 쓴다.
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        return [
            'documents' => ['nullable', 'array', 'max:'.self::MAX_FILES],
            'documents.*' => ['file', 'mimes:'.WorkerFile::MIMES, 'max:'.WorkerFile::MAX_KB],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'documents.max' => '파일은 한 번에 '.self::MAX_FILES.'개까지 올릴 수 있습니다.',
        ];
    }

    /**
     * 올린 파일을 근로자 서류로 저장한다.
     *
     * @param  list<UploadedFile>  $files
     */
    public static function store(array $files, Worker $worker, ?string $note = null): int
    {
        $saved = 0;

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');

            // 저장 이름은 ASCII 로 만든다. 한글 파일명은 서버·백업에서 깨진다.
            $name = 'apply_'.Str::random(16).'.'.$ext;

            $path = $file->storeAs(
                WorkerFile::DIR.'/'.$worker->id,
                $name,
                ['disk' => WorkerFile::DISK],
            );

            WorkerFile::create([
                'worker_id' => $worker->id,
                'type' => WorkerFileType::Other,
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'size' => Storage::disk(WorkerFile::DISK)->size($path),
                'mime' => $file->getClientMimeType(),
                'note' => $note,
                // 본인이 올렸다. 담당자가 올린 것과 구분되도록 비워 둔다.
                'uploaded_by' => null,
            ]);

            $saved++;
        }

        return $saved;
    }
}
