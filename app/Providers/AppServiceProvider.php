<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Demand\Models\DemandRequest;
use App\Domains\Demand\Policies\DemandRequestPolicy;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Onboarding\Policies\OnboardingSubmissionPolicy;
use App\Models\Setting;
use App\Support\Livewire\BasePathHandleRequests;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

class AppServiceProvider extends ServiceProvider
{
    /**
     * 도메인 모델은 표준 app/Models 밖에 있어 정책 자동탐색이 안 되므로 명시적으로 매핑한다.
     *
     * @var array<class-string, class-string>
     */
    private array $policies = [
        DemandRequest::class => DemandRequestPolicy::class,
        OnboardingSubmission::class => OnboardingSubmissionPolicy::class,
    ];

    public function register(): void
    {
        // 서브폴더 배포에서 Livewire update URI 에 base path(/ndn)를 포함시키는 보정.
        $this->app->singleton(
            HandleRequests::class,
            BasePathHandleRequests::class,
        );
    }

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // 도메인 모델이 app/Models 밖(app/Domains/*/Models)에 있어 기본 팩토리 이름
        // 추론이 어긋난다. 팩토리는 전부 Database\Factories\{클래스명}Factory 이므로
        // 모델 클래스 basename 만으로 팩토리를 찾도록 리졸버를 교체한다.
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Database\\Factories\\'.Str::afterLast($modelName, '\\').'Factory'
        );

        // N+1 조기 발견 (CLAUDE.md §11: preventLazyLoading 활성 유지).
        // 운영에서는 예외를 던지지 않고 로깅만 하도록 두는 편이 안전하므로 로컬에서만 엄격.
        Model::preventLazyLoading(! app()->isProduction());

        // 회사소개 사이트 편집 콘텐츠(통계·사업자정보·연락처)를 전역 공유.
        // 블레이드에서 $S['key'] 로 접근. (캐시되므로 요청당 쿼리 1회 이하)
        View::share('S', Setting::allKeyed());

        // 서브폴더(/ndn) 배포 보정 (Filament·Livewire 관리 콘솔이 정상 동작하게 함):
        //  1) Livewire 스크립트 src 는 라우트 URI 를 그대로 써서 base(/ndn)가 빠진 채
        //     /livewire/livewire.js 로 로드돼 404 → asset_url 을 base 포함으로 지정.
        //  2) update 엔드포인트 URI 도 base 가 빠지므로 BasePathHandleRequests 로 보정
        //     (register() 에서 바인딩).
        $basePath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        if ($basePath !== '') {
            config(['livewire.asset_url' => $basePath.'/livewire/livewire.js']);
        }
    }
}
