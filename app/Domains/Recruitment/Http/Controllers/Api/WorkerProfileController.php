<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Controllers\Api;

use App\Domains\Recruitment\Http\Resources\WorkerResource;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * 근로자 앱 — 본인 프로필 (CLAUDE.md §9).
 * 인증된 Worker 본인에서만 파생하므로 스코프가 자동 보장된다.
 */
class WorkerProfileController extends Controller
{
    public function show(Request $request): WorkerResource
    {
        /** @var Worker $worker */
        $worker = $request->user();

        return (new WorkerResource($worker))
            ->additional(['meta' => ['locale' => $worker->locale]]);
    }
}
