<?php

declare(strict_types=1);

/*
 * 로고 파생 파일 생성기.
 *
 *   php resources/brand/build.php
 *
 * 원본(logo-source.png)은 디자인에서 받은 그대로다 — 흰 바탕, 여백 넓음,
 * 2176x1632. 그대로는 웹에 못 쓴다.
 *
 *   - 어두운 화면(콘솔 사이드바·로그인 옆판·에러 페이지)에 얹으면 흰 네모가 뜬다
 *   - 여백 때문에 정작 글자가 작게 보인다
 *   - 900KB 를 헤더마다 내려받게 할 수는 없다
 *
 * 그래서 여백을 잘라내고, 흰 바탕을 투명으로 바꾸고, 어두운 면용으로
 * **검정 글자를 흰색으로 바꾼** 한 벌을 더 만든다. 금색은 어두운 데서도
 * 읽히므로 두 벌 모두 그대로 둔다.
 *
 * 로고가 새로 오면 logo-source.png 만 갈아 끼우고 이 스크립트를 다시 돌린다.
 * 손으로 만든 파일이 섞이면 다음 사람이 무엇을 고쳐야 할지 알 수 없다.
 */

const SRC = __DIR__.'/logo-source.png';
const OUT = __DIR__.'/../../public/site/assets/';

/** 이보다 밝으면 배경으로 본다. 순백만 배경으로 보면 가장자리에 회색 테가 남는다. */
const BG_MIN = 235;

/** 이보다 어두우면 '검정 글자'로 본다 (금색은 이 선보다 밝다). */
const INK_MAX = 110;

/** 파비콘 판 색 — 브랜드 잉크. */
const PLATE = [0x1A, 0x14, 0x0F];

$src = imagecreatefrompng(SRC);
$sw = imagesx($src);
$sh = imagesy($src);

/* ---------- 1) 여백 잘라내기 ---------- */
[$x0, $y0, $cw, $ch] = trimBounds($src, $sw, $sh);
echo "원본 {$sw}x{$sh} → 여백 제거 {$cw}x{$ch}\n";

/* ---------- 2) 밝은 면용 / 어두운 면용 ---------- */
foreach (['logo.png' => false, 'logo-light.png' => true] as $name => $whiteInk) {
    $img = render($src, $x0, $y0, $cw, $ch, 480, $whiteInk);
    imagepng($img, OUT.$name, 9);
    report($name, $img);
}

/* ---------- 3) 파비콘 — 잉크 판에 흰 로고 ----------
 * 투명 파비콘은 밝은 탭에서 검은 글자가, 어두운 탭에서 흰 글자가 사라진다.
 * 판을 깔아 두면 브라우저 테마와 무관하게 같은 모습이다. */
foreach ([32 => 'favicon-32.png', 180 => 'apple-touch-icon.png'] as $size => $name) {
    $img = plate($src, $x0, $y0, $cw, $ch, $size);
    imagepng($img, OUT.$name, 9);
    report($name, $img);
}

/* ---------- 4) favicon.svg — 벡터 자리에 작은 PNG 를 넣는다 ----------
 * 로고가 기하 도형이라 손으로 path 를 그리면 원본과 미세하게 어긋난다.
 * 팔레트로 줄인 200px PNG 를 담으면 2KB 대에서 원본과 정확히 같다. */
svg($src, $x0, $y0, $cw, $ch);

/* ---------- 5) favicon.ico — 주소창에 직접 요청되는 옛 형식 ----------
 * 어느 화면에서도 <link> 로 걸지 않지만 브라우저가 /favicon.ico 를 그냥 가져간다.
 * 여기만 옛 로고로 남으면 탭이 두 가지 얼굴을 갖는다. */
ico($src, $x0, $y0, $cw, $ch);

echo "완료\n";

/* ==================================================================== */

/**
 * 배경이 아닌 픽셀이 처음/마지막으로 나타나는 지점.
 *
 * @return array{int, int, int, int} x, y, 너비, 높이
 */
function trimBounds(GdImage $img, int $w, int $h): array
{
    $minX = $w;
    $minY = $h;
    $maxX = -1;
    $maxY = -1;

    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x++) {
            [$r, $g, $b] = rgb($img, $x, $y);

            if ($r >= BG_MIN && $g >= BG_MIN && $b >= BG_MIN) {
                continue;
            }

            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
        }
    }

    // 글자가 가장자리에 딱 붙지 않도록 아주 조금만 남긴다.
    $pad = 6;
    $minX = max(0, $minX - $pad);
    $minY = max(0, $minY - $pad);
    $maxX = min($w - 1, $maxX + $pad);
    $maxY = min($h - 1, $maxY + $pad);

    return [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
}

/** 잘라내고, 흰 바탕을 투명으로 바꾸고, 쓸 크기로 줄인다. */
function render(GdImage $src, int $x0, int $y0, int $cw, int $ch, int $targetW, bool $whiteInk): GdImage
{
    $cut = canvas($cw, $ch);
    imagecopy($cut, $src, 0, 0, $x0, $y0, $cw, $ch);

    for ($y = 0; $y < $ch; $y++) {
        for ($x = 0; $x < $cw; $x++) {
            [$r, $g, $b] = rgb($cut, $x, $y);

            if ($r >= BG_MIN && $g >= BG_MIN && $b >= BG_MIN) {
                imagesetpixel($cut, $x, $y, imagecolorallocatealpha($cut, 255, 255, 255, 127));

                continue;
            }

            if ($whiteInk && $r <= INK_MAX && $g <= INK_MAX && $b <= INK_MAX) {
                imagesetpixel($cut, $x, $y, imagecolorallocate($cut, 255, 255, 255));
            }
        }
    }

    $targetH = (int) round($ch * ($targetW / $cw));
    $out = canvas($targetW, $targetH);
    imagealphablending($out, true);
    imagecopyresampled($out, $cut, 0, 0, 0, 0, $targetW, $targetH, $cw, $ch);

    return $out;
}

/** 정사각 잉크 판 가운데에 흰 로고를 얹는다. */
function plate(GdImage $src, int $x0, int $y0, int $cw, int $ch, int $size): GdImage
{
    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocate($out, ...PLATE));

    $inner = (int) round($size * 0.78);
    $mark = render($src, $x0, $y0, $cw, $ch, $inner, true);
    $ih = imagesy($mark);

    imagecopy($out, $mark, (int) (($size - $inner) / 2), (int) (($size - $ih) / 2), 0, 0, $inner, $ih);

    return $out;
}

/** ico 한 칸에 넣을 PNG 바이트. */
function icoFrame(GdImage $src, int $x0, int $y0, int $cw, int $ch, int $size): string
{
    $out = plate($src, $x0, $y0, $cw, $ch, $size);

    ob_start();
    imagepng($out, null, 9);

    return (string) ob_get_clean();
}

/** 32x32 판 안에 로고를 앉힌 SVG. */
function svg(GdImage $src, int $x0, int $y0, int $cw, int $ch): void
{
    $mark = render($src, $x0, $y0, $cw, $ch, 200, true);
    // 흰색·금색·투명 세 가지뿐이라 팔레트로 줄여도 눈에 띄는 손실이 없다.
    // 트루컬러 그대로면 13KB, 줄이면 2KB — 파비콘 한 장 값으로는 큰 차이다.
    imagetruecolortopalette($mark, true, 32);
    imagesavealpha($mark, true);

    ob_start();
    imagepng($mark, null, 9);
    $b64 = base64_encode((string) ob_get_clean());

    $w = round(32 * 0.78, 2);
    $h = round($w * (imagesy($mark) / imagesx($mark)), 2);
    $x = round((32 - $w) / 2, 2);
    $y = round((32 - $h) / 2, 2);
    $plate = sprintf('#%02X%02X%02X', ...PLATE);

    $svg = <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="32" height="32" viewBox="0 0 32 32">
          <title>N.D.N Korea</title>
          <rect width="32" height="32" rx="6.5" fill="{$plate}"/>
          <image x="{$x}" y="{$y}" width="{$w}" height="{$h}" xlink:href="data:image/png;base64,{$b64}"/>
        </svg>

        SVG;

    file_put_contents(OUT.'favicon.svg', $svg);
    echo 'favicon.svg  ', round(strlen($svg) / 1024, 1), "KB\n";
}

/**
 * 여러 크기를 담은 favicon.ico.
 *
 * 각 칸에 PNG 를 그대로 넣는다 (Vista 이후 형식). 요즘 브라우저는 모두 읽고,
 * BMP + AND 마스크를 직접 짜는 것보다 틀릴 여지가 적다.
 */
function ico(GdImage $src, int $x0, int $y0, int $cw, int $ch): void
{
    $sizes = [16, 32, 48, 64, 128, 256];
    $frames = array_map(fn (int $s) => icoFrame($src, $x0, $y0, $cw, $ch, $s), $sizes);

    $header = pack('vvv', 0, 1, count($sizes));
    $offset = 6 + 16 * count($sizes);

    $dir = '';
    foreach ($sizes as $i => $s) {
        $dir .= pack(
            'CCCCvvVV',
            $s >= 256 ? 0 : $s,     // 256 은 0 으로 적는 게 규격이다
            $s >= 256 ? 0 : $s,
            0, 0, 1, 32,
            strlen($frames[$i]),
            $offset,
        );
        $offset += strlen($frames[$i]);
    }

    $ico = $header.$dir.implode('', $frames);
    file_put_contents(__DIR__.'/../../public/favicon.ico', $ico);
    echo 'favicon.ico  ', count($sizes), '장  ', round(strlen($ico) / 1024, 1), "KB\n";
}

/** 알파를 살린 빈 캔버스. */
function canvas(int $w, int $h): GdImage
{
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));

    return $img;
}

/** @return array{int, int, int} */
function rgb(GdImage $img, int $x, int $y): array
{
    $c = imagecolorat($img, $x, $y);

    return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
}

function report(string $name, GdImage $img): void
{
    printf("%-22s %dx%d  %sKB\n", $name, imagesx($img), imagesy($img),
        round(filesize(OUT.$name) / 1024, 1));
}
