<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Actions;

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * 근로자 앱 로그인 → Sanctum 토큰 발급 (CLAUDE.md §9).
 *
 * 비즈니스 로직은 Action 에 둔다(§4). 컨트롤러는 검증·호출·응답만 한다.
 */
class LoginWorkerAction
{
    /**
     * @return array{worker: Worker, token: string}
     *
     * @throws ValidationException 자격증명이 맞지 않거나 비활성 계정일 때
     */
    public function execute(string $email, string $password, ?string $deviceName = null): array
    {
        $worker = Worker::where('email', $email)->first();

        // 계정 존재 여부가 응답 차이로 새어나가지 않도록 동일한 오류로 처리한다.
        if ($worker === null || $worker->password === null || ! Hash::check($password, $worker->password)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.failed')],
            ]);
        }

        // 승인 대기·거절·정지·귀국 계정은 로그인 불가 (활성만 허용).
        if (! $worker->status->canLogin()) {
            // 승인 대기 계정은 근로자 자국어로 안내(§6). 그 외는 자격증명 오류로 통일.
            $message = $worker->status === WorkerStatus::Pending
                ? trans('worker.pending_approval', [], $worker->locale ?: 'ko')
                : __('auth.failed');

            throw ValidationException::withMessages(['email' => [$message]]);
        }

        // 기기별 토큰 — 같은 기기로 재로그인하면 기존 토큰을 정리한다.
        $device = $deviceName ?: 'worker-app';
        $worker->tokens()->where('name', $device)->delete();

        return [
            'worker' => $worker,
            'token' => $worker->createToken($device)->plainTextToken,
        ];
    }
}
