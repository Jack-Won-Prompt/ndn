<?php

declare(strict_types=1);

namespace App\Domains\Support\Http\Controllers\Api;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Actions\CreateSosAlertAction;
use App\Domains\Support\Http\Requests\StoreSosRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * 근로자 앱 — 긴급 SOS (CLAUDE.md §9).
 *
 * 좌표는 요청 본문으로만 수신한다. 인증된 Worker 본인에 대해서만 SOS 를 생성한다.
 */
class SosController extends Controller
{
    public function store(StoreSosRequest $request, CreateSosAlertAction $action): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $alert = $action->execute(
            $worker,
            $request->float('lat') ?: null,
            $request->float('lng') ?: null,
        );

        return response()->json([
            'data' => [
                'id' => $alert->id,
                'status' => $alert->status,
                'alerted_at' => $alert->alerted_at?->toIso8601String(),
            ],
            'meta' => ['message' => __('worker.sos_received', [], $worker->locale)],
        ], 201);
    }
}
