<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Shared\Support\IpCountry;
use Illuminate\Console\Command;

/**
 * 국가 판별표 상태 확인 · 시험 조회.
 *
 * 접속 로그의 '접속 국가' 는 storage/app/geoip/ip-country.csv 로 판별한다.
 * 파일을 새로 넣거나 갈아 끼운 뒤 제대로 읽히는지 여기서 확인한다.
 */
class GeoipStatus extends Command
{
    protected $signature = 'ndn:geoip-status {ip?* : 시험 삼아 판별해 볼 IP}';

    protected $description = '접속 국가 판별표가 깔려 있는지 확인하고, 주어진 IP를 판별해 본다';

    public function handle(): int
    {
        $path = storage_path('app/'.IpCountry::DATA_PATH);

        if (! IpCountry::hasData()) {
            $this->warn('국가 판별표가 없습니다: '.$path);
            $this->newLine();
            $this->line('  무료로 재배포되는 국가 대역표를 내려받아 위 경로에 두세요.');
            $this->line('  형식: start_ip,end_ip,country_code  (숫자 IP 범위, ISO-2)');
            $this->line('  예: DB-IP Lite Country, IP2Location LITE DB1');
            $this->newLine();
            $this->line('  없어도 서비스는 동작합니다 — 사내·같은 장비는 [내부],');
            $this->line('  나머지는 [미상] 으로 기록됩니다.');

            return self::FAILURE;
        }

        $this->info('판별표: '.$path);
        $this->line('  크기: '.number_format((int) filesize($path)).' bytes');
        $this->line('  수정: '.date('Y-m-d H:i:s', (int) filemtime($path)));

        /** @var list<string> $ips */
        $ips = (array) $this->argument('ip');
        if ($ips === []) {
            return self::SUCCESS;
        }

        $this->newLine();
        foreach ($ips as $ip) {
            $code = IpCountry::of($ip);
            $this->line(sprintf('  %-18s → %s (%s)', $ip, IpCountry::label($code), $code ?? 'null'));
        }

        return self::SUCCESS;
    }
}
