<?php

declare(strict_types=1);

namespace App\Support\Livewire;

use Livewire\Mechanisms\HandleRequests\HandleRequests;

/**
 * 서브폴더(/ndn) 배포에서 Livewire update 엔드포인트 URI 를 보정한다.
 *
 * Livewire 기본 getUpdateUri() 는 app('url')->toRoute($route, [], false) 를 쓰는데,
 * Laravel 의 "상대 경로" 라우트 생성은 배포 base path(/ndn)를 포함하지 않아
 * /livewire/update 로 나가 404 가 난다. 요청 base 경로를 앞에 붙여 바로잡는다.
 * (JS 자산 URL 은 config('livewire.asset_url') 로 별도 보정 — AppServiceProvider)
 */
class BasePathHandleRequests extends HandleRequests
{
    public function getUpdateUri(): string
    {
        $base = request()->getBaseUrl(); // 웹 요청에서 '/ndn', 루트 배포면 ''

        return $base.'/livewire/update';
    }
}
