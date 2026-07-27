<?php

declare(strict_types=1);

namespace App\Domains\Settlement\Support;

/**
 * 대리점 문서 다운로드 워터마크 (CLAUDE.md §7-5).
 *
 * 이미지(jpg/png/webp/gif)에 대리점명을 반투명 대각선으로 반복 삽입한다.
 * GD 확장이 없거나 이미지가 아니면 null 을 반환(호출측이 원본 스트리밍으로 폴백).
 */
class DocumentWatermark
{
    /** 이미지 바이너리에 워터마크를 입혀 PNG 바이너리로 반환. 실패 시 null. */
    public static function stampImage(string $binary, string $label): ?string
    {
        if (! \function_exists('imagecreatefromstring')) {
            return null;
        }

        $img = @imagecreatefromstring($binary);
        if ($img === false) {
            return null;
        }

        $w = imagesx($img);
        $h = imagesy($img);
        imagealphablending($img, true);

        $white = imagecolorallocatealpha($img, 255, 255, 255, 90);
        $dark = imagecolorallocatealpha($img, 20, 30, 40, 90);

        $text = $label.'  ·  대외비';
        $step = max(120, (int) ($w / 4));
        for ($y = 0; $y < $h + $step; $y += $step) {
            for ($x = -$step; $x < $w; $x += $step * 2) {
                // 그림자 + 본문 (가독성 위해 두 번)
                imagestring($img, 5, $x + 1, $y + 1, $text, $dark);
                imagestring($img, 5, $x, $y, $text, $white);
            }
        }

        ob_start();
        imagepng($img);
        $out = (string) ob_get_clean();
        imagedestroy($img);

        return $out !== '' ? $out : null;
    }
}
