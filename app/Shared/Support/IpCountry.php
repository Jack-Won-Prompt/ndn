<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Support\Facades\Cache;

/**
 * 접속 IP → 국가 (로컬 판별).
 *
 * 서버 안에서만 판별한다. 접속자 IP 를 외부 조회 서비스로 보내지 않는다 —
 * 그건 그 자체로 제3자 제공이 된다(§7-4).
 *
 * 이건 **요청이 어디서 들어왔는지**를 남기는 보안 감사용이고, 근로자의 이동을
 * 따라가는 위치 추적이 아니다(§7-2). 나라 단위까지만 알며 좌표는 다루지 않는다.
 * 근로자 위치는 여전히 SosAlert 와 InspectionCheckin 두 곳에만 있다.
 *
 * 판별 근거는 `storage/app/geoip/ip-country.csv` 다 (start_ip,end_ip,country_code).
 * DB-IP Lite·IP2Location LITE 처럼 무료로 재배포되는 CSV 를 그대로 쓸 수 있다.
 * 파일이 없으면 사설 대역만 가려내고 나머지는 '미상'으로 둔다 — 없는 근거로
 * 나라를 지어내지 않는다.
 */
class IpCountry
{
    /** 사내·로컬에서 들어온 요청. 국가가 아니므로 코드와 구분해 쓴다. */
    public const LOCAL = 'LOCAL';

    public const DATA_PATH = 'geoip/ip-country.csv';

    /** 판별 결과(ISO-2) 또는 LOCAL, 알 수 없으면 null. */
    public static function of(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }

        if (self::isLocal($ip)) {
            return self::LOCAL;
        }

        $long = ip2long($ip);
        if ($long === false) {
            return null;   // IPv6 는 아직 표를 두지 않았다
        }

        return self::lookup(sprintf('%u', $long));
    }

    /** 화면에 보일 이름. */
    public static function label(?string $code): string
    {
        if ($code === null) {
            return '미상';
        }
        if ($code === self::LOCAL) {
            return '내부';
        }

        return self::NAMES[$code] ?? $code;
    }

    /** 판별표가 깔려 있는가 (콘솔에서 안내 문구를 바꾸는 데 쓴다). */
    public static function hasData(): bool
    {
        return is_file(storage_path('app/'.self::DATA_PATH));
    }

    /**
     * 사설·루프백·링크로컬 — 사내망이나 같은 장비에서 온 요청.
     * 운영이 리버스 프록시 뒤에 있으면 TrustProxies 설정에 따라 실제 IP 가 온다.
     */
    private static function isLocal(string $ip): bool
    {
        if ($ip === '::1' || $ip === '127.0.0.1') {
            return true;
        }

        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) === false;
    }

    /**
     * 정렬된 대역표에서 이진 탐색.
     *
     * 표는 한 번 읽어 캐시에 올린다. 파일이 수십만 줄이라 요청마다 읽으면
     * 접속 로그 기록이 요청보다 무거워진다.
     *
     * @param  string  $long  부호 없는 32비트 값(문자열로 다룬다)
     */
    private static function lookup(string $long): ?string
    {
        $ranges = self::ranges();
        if ($ranges === []) {
            return null;
        }

        $target = (int) $long;
        $lo = 0;
        $hi = count($ranges) - 1;

        while ($lo <= $hi) {
            $mid = intdiv($lo + $hi, 2);
            [$start, $end, $code] = $ranges[$mid];

            if ($target < $start) {
                $hi = $mid - 1;
            } elseif ($target > $end) {
                $lo = $mid + 1;
            } else {
                return $code;
            }
        }

        return null;
    }

    /**
     * @return list<array{0:int,1:int,2:string}> start, end, ISO-2 (start 오름차순)
     */
    private static function ranges(): array
    {
        $path = storage_path('app/'.self::DATA_PATH);
        if (! is_file($path)) {
            return [];
        }

        /*
         * 파일 자체를 키로 삼는다(수정 시각·크기). 표를 갈아 끼우면 키가 달라져
         * 저절로 다시 읽힌다.
         *
         * 결과를 static 변수에 붙들지 않는다 — 붙들면 '파일 없음'까지 기억해서,
         * 표를 나중에 넣어도 프로세스를 다시 띄우기 전에는 반영되지 않는다
         * (큐 워커처럼 오래 사는 프로세스에서 문제가 된다).
         */
        $key = 'ip_country:'.filemtime($path).':'.filesize($path);

        try {
            return Cache::remember($key, now()->addDay(), fn () => self::readCsv($path));
        } catch (\Throwable) {
            // 캐시를 못 써도 판별은 되어야 한다. 접속 기록이 통째로 막히면 안 된다.
            return self::readCsv($path);
        }
    }

    /** @return list<array{0:int,1:int,2:string}> */
    private static function readCsv(string $path): array
    {
        $rows = [];
        $fh = fopen($path, 'r');
        if ($fh === false) {
            return [];
        }

        while (($cols = fgetcsv($fh)) !== false) {
            if (count($cols) < 3) {
                continue;
            }
            // IPv6 줄이 섞여 있으면 건너뛴다 (숫자가 아니거나 32비트를 넘는 값)
            if (! is_numeric($cols[0]) || ! is_numeric($cols[1])) {
                continue;
            }
            $start = (int) $cols[0];
            $end = (int) $cols[1];
            $code = strtoupper(trim((string) $cols[2]));
            if ($code === '' || $code === '-' || strlen($code) !== 2) {
                continue;
            }
            $rows[] = [$start, $end, $code];
        }
        fclose($fh);

        usort($rows, fn ($a, $b) => $a[0] <=> $b[0]);

        return $rows;
    }

    /** 화면에 자주 나올 나라만. 없는 코드는 코드 그대로 보여 준다. */
    private const NAMES = [
        'KR' => '대한민국',
        'VN' => '베트남',
        'BD' => '방글라데시',
        'LA' => '라오스',
        'LK' => '스리랑카',
        'NP' => '네팔',
        'KG' => '키르기스스탄',
        'US' => '미국',
        'CN' => '중국',
        'JP' => '일본',
        'PH' => '필리핀',
        'TH' => '태국',
        'ID' => '인도네시아',
        'IN' => '인도',
        'KH' => '캄보디아',
        'MM' => '미얀마',
        'UZ' => '우즈베키스탄',
        'MN' => '몽골',
        'RU' => '러시아',
        'HK' => '홍콩',
        'SG' => '싱가포르',
        'DE' => '독일',
        'NL' => '네덜란드',
        'GB' => '영국',
    ];
}
