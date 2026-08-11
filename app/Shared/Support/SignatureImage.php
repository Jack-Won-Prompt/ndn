<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 서명 캔버스 결과(base64 PNG)를 private 저장소에 넣는다.
 *
 * 온보딩 전자서명과 근무상태 점검표 서명이 같은 방식을 쓴다. 서명은 본인을
 * 특정하는 개인정보라 public/ 에 두지 않고 경로만 DB 에 남긴다(§9).
 */
class SignatureImage
{
    /** private 디스크. 서명 URL 로 직접 접근되지 않는다. */
    public const DISK = 'local';

    /**
     * 받아 줄 최대 크기.
     *
     * 서명 캔버스 PNG 는 보통 수십 KB 다. 이보다 크면 캔버스 결과가 아니라
     * 사진이나 장난이므로 받지 않는다 — 저장소가 조용히 차오르는 걸 막는다.
     */
    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * base64(data URL 허용) → 저장 후 경로. 유효하지 않으면 null.
     *
     * @param  string  $dir  저장할 디렉터리 (예: 'work-reviews/12/signatures')
     * @param  string  $name  확장자 없는 파일 이름 (예: 'worker')
     */
    public static function store(?string $raw, string $dir, string $name): ?string
    {
        $binary = self::decode($raw);
        if ($binary === null) {
            return null;
        }

        // 이름이 겹쳐도 덮어쓰지 않게 뒤에 짧은 무작위를 붙인다. 다시 서명하면
        // 새 파일이 되고, 예전 서명은 남는다(무엇에 서명했는지의 증빙이다).
        $path = trim($dir, '/').'/'.$name.'_'.Str::random(8).'.'.self::extension($binary);

        Storage::disk(self::DISK)->put($path, $binary);

        return $path;
    }

    /** 저장된 서명의 MIME — 스트리밍할 때 쓴다. */
    public static function mime(?string $path): string
    {
        return str_ends_with((string) $path, '.jpg') ? 'image/jpeg' : 'image/png';
    }

    /** 바이너리에서 확장자. decode() 를 통과한 값만 들어온다. */
    private static function extension(string $binary): string
    {
        return str_starts_with($binary, "\xFF\xD8\xFF") ? 'jpg' : 'png';
    }

    /**
     * base64(data URL 허용) → 이미지 바이너리. 아니면 null.
     *
     * 서명 캔버스는 PNG 를 만들지만, 사진으로 찍어 올리는 앱 화면이 있을 수 있어
     * JPEG 도 받는다. 형식을 PNG 하나로 좁히면 그런 클라이언트의 서명이 조용히
     * 사라진다 — 서명은 증빙이라 소리 없이 버리면 안 된다.
     */
    public static function decode(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, ',')) {
            $raw = substr($raw, strpos($raw, ',') + 1);
        }

        // 캔버스가 비어 있어도 data URL 은 만들어진다. 디코드 전에 길이로 한 번 거른다.
        if (strlen($raw) > self::MAX_BYTES * 2) {
            return null;
        }

        $binary = base64_decode(strtr($raw, ' ', '+'), true);
        if ($binary === false || $binary === '') {
            return null;
        }

        if (strlen($binary) > self::MAX_BYTES) {
            return null;
        }

        // 매직 넘버 확인 — 엉뚱한 바이트를 이미지인 척 받아 두지 않는다.
        $isPng = str_starts_with($binary, "\x89PNG\r\n\x1a\n");
        $isJpeg = str_starts_with($binary, "\xFF\xD8\xFF");
        if (! $isPng && ! $isJpeg) {
            return null;
        }

        return $binary;
    }

    /** 저장된 서명이 실제로 있는가. */
    public static function exists(?string $path): bool
    {
        return filled($path) && Storage::disk(self::DISK)->exists($path);
    }
}
