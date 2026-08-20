<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Controllers\Api;

use App\Domains\Recruitment\Actions\LoginWorkerAction;
use App\Domains\Recruitment\Actions\RegisterWorkerAction;
use App\Domains\Recruitment\Http\Requests\RegisterWorkerRequest;
use App\Domains\Recruitment\Http\Requests\WorkerLoginRequest;
use App\Domains\Recruitment\Http\Resources\WorkerResource;
use App\Domains\Recruitment\Models\Worker;
use App\Domains\Recruitment\Support\ApplicationDocuments;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 근로자 앱 — 인증 (CLAUDE.md §9).
 *
 * 로그인은 토큰이 없는 상태에서 호출하므로 auth 미들웨어 밖에 둔다.
 * 로그아웃은 현재 토큰을 폐기한다.
 */
class AuthController extends Controller
{
    /**
     * 근로자 셀프 가입 (관리자 승인제). 가입 후 status=pending → 승인 전에는 로그인 불가.
     * 토큰은 발급하지 않는다(승인 후 로그인에서 발급).
     */
    public function register(RegisterWorkerRequest $request, RegisterWorkerAction $action): JsonResponse
    {
        $worker = $action->execute($request->validated());

        // 가입과 함께 올라온 서류. 웹(/apply)과 같은 자리에 같은 모양으로 넣는다.
        // 계정을 만든 뒤에 저장한다 — worker_id 가 있어야 경로가 정해진다.
        $files = ApplicationDocuments::store($request->file('documents') ?? [], $worker);

        return response()->json([
            'data' => [
                'id' => $worker->id,
                'status' => $worker->status->value,
                'documents' => $files,
            ],
            'meta' => [
                // 근로자 자국어 안내 (§6)
                'message' => trans('worker.register_pending', [], $worker->locale ?: 'ko'),
            ],
        ], 201);
    }

    public function login(WorkerLoginRequest $request, LoginWorkerAction $action): JsonResponse
    {
        ['worker' => $worker, 'token' => $token] = $action->execute(
            $request->string('email')->value(),
            $request->string('password')->value(),
            $request->input('device_name'),
        );

        return response()->json([
            'data' => (new WorkerResource($worker))->toArray($request),
            'meta' => [
                'token' => $token,
                'locale' => $worker->locale,
            ],
        ]);
    }

    /** 현재 기기의 토큰만 폐기 */
    public function logout(Request $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();
        $worker->currentAccessToken()?->delete();

        return response()->json(['data' => ['message' => 'ok']]);
    }
}
