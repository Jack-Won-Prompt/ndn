<?php

declare(strict_types=1);

namespace App\Domains\Support\Http\Controllers\Api;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\WorkerGuide;
use App\Domains\Support\Services\WorkerGuidePresenter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 근로자 앱 — 안내 자료 (사전교육·긴급 연락처·의료기관).
 *
 * 읽기 전용이다. 동의를 받는 문서는 RequiredDocumentController 쪽이다.
 * 본문이 길어 목록과 본문을 나눈다 — 목록만 받아 화면을 그리고, 고른 것만 연다.
 */
class WorkerGuideController extends Controller
{
    public function index(Request $request, WorkerGuidePresenter $presenter): JsonResponse
    {
        $locale = $this->locale($request);

        return response()->json([
            'data' => $presenter->index($locale),
            'meta' => ['locale' => $locale],
        ]);
    }

    /** key 로 연다 — 자료가 늘거나 순서가 바뀌어도 앱의 링크가 살아 있게. */
    public function show(Request $request, string $key, WorkerGuidePresenter $presenter): JsonResponse
    {
        $guide = WorkerGuide::query()->active()->where('key', $key)->firstOrFail();

        $locale = $this->locale($request);

        return response()->json([
            'data' => $presenter->show($guide, $locale),
            'meta' => ['locale' => $locale],
        ]);
    }

    private function locale(Request $request): string
    {
        /** @var Worker $worker */
        $worker = $request->user();

        return $worker->locale ?: 'ko';
    }
}
