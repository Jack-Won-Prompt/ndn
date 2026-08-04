<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * 사이트 설정 키-값. 회사소개 사이트의 편집 가능한 내용을 담는다.
 *
 * 편집 가능한 필드는 fields() 에 그룹·라벨과 함께 정의한다. 사이트 블레이드는
 * 전역 공유되는 $S 배열(AppServiceProvider)로 값을 읽는다.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'site_settings.all';

    /**
     * 편집 폼 정의 — 그룹별 필드(키·라벨·타입·플레이스홀더).
     *
     * @return array<int, array{group: string, fields: array<int, array{key:string,label:string,type?:string,ph?:string}>}>
     */
    public static function fields(): array
    {
        return [
            [
                'group' => '홈 화면',
                'fields' => [
                    // public/site/assets/img/ 안의 파일명만 넣는다 (예: harvest.jpg).
                    // 비워 두면 사진 없이 그라디언트 배경으로 나온다.
                    // 라이선스가 확보된 파일만 쓸 것 — public/site/README.md 사진 감사 결과 참조.
                    ['key' => 'site.hero_image', 'label' => '히어로 배경 사진 (파일명)',
                        'ph' => '예: harvest.jpg · 비우면 사진 없음'],
                ],
            ],
            [
                'group' => '홈 통계',
                'fields' => [
                    ['key' => 'stats.countries',   'label' => '송출 협력국 (개국)', 'ph' => '예: 4'],
                    ['key' => 'stats.workers',     'label' => '누적 입국 근로자 (명)', 'ph' => '예: 320'],
                    ['key' => 'stats.cities',      'label' => '협약 지자체 (개)', 'ph' => '예: 6'],
                    ['key' => 'stats.return_rate', 'label' => '계약 만료 귀국률 (%)', 'ph' => '예: 98'],
                ],
            ],
            [
                'group' => '사업자 정보',
                'fields' => [
                    ['key' => 'company.ceo',     'label' => '대표이사'],
                    ['key' => 'company.biz_no',  'label' => '사업자등록번호', 'ph' => '000-00-00000'],
                    ['key' => 'company.address', 'label' => '주소'],
                    ['key' => 'company.phone',   'label' => '대표전화', 'ph' => '00-0000-0000'],
                    ['key' => 'company.email',   'label' => '이메일', 'type' => 'email'],
                ],
            ],
            [
                'group' => '연락처 (문의 페이지)',
                'fields' => [
                    ['key' => 'contact.address', 'label' => '주소'],
                    ['key' => 'contact.phone',   'label' => '대표전화'],
                    ['key' => 'contact.email',   'label' => '이메일', 'type' => 'email'],
                    ['key' => 'contact.hours',   'label' => '운영 시간', 'ph' => '평일 09:00–18:00'],
                ],
            ],
            [
                'group' => '모바일 앱',
                'fields' => [
                    // 비우면 앱 다운로드(QR·설치 링크)가 홈페이지로 연결된다.
                    // 플레이스토어 등록 후 전체 URL 을 넣으면 자동으로 플레이스토어로 연결된다.
                    ['key' => 'app.play_store_url', 'label' => '플레이스토어 URL', 'type' => 'url',
                        'ph' => 'https://play.google.com/store/apps/details?id=...'],
                ],
            ],
        ];
    }

    /** @return array<string, string> 전체 설정 (캐시) */
    public static function allKeyed(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => self::query()->pluck('value', 'key')->all());
        } catch (\Throwable) {
            // 마이그레이션 이전 등 테이블 부재 시 안전하게 빈 배열
            return [];
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $v = self::allKeyed()[$key] ?? null;

        return ($v === null || $v === '') ? $default : $v;
    }

    public static function put(string $key, ?string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    /** @return list<string> 정의된 모든 편집 키 */
    public static function allKeys(): array
    {
        $keys = [];
        foreach (self::fields() as $group) {
            foreach ($group['fields'] as $f) {
                $keys[] = $f['key'];
            }
        }

        return $keys;
    }
}
