<?php

declare(strict_types=1);

namespace App\Domains\Onboarding\Http\Controllers\Api;

use App\Domains\Onboarding\Actions\SubmitOnboardingAction;
use App\Domains\Onboarding\Enums\OnboardingStatus;
use App\Domains\Onboarding\Http\Resources\OnboardingResource;
use App\Domains\Onboarding\Models\OnboardingSubmission;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 근로자 앱 — 셀프 온보딩 (CLAUDE.md §9).
 *
 * 모든 조작은 인증된 Worker 본인의 제출물에 대해서만 이루어진다(스코프 자동 보장).
 */
class OnboardingController extends Controller
{
    /** 본인의 최신 온보딩 제출물 */
    public function show(Request $request): OnboardingResource|JsonResponse
    {
        $submission = $this->latestFor($request);

        if ($submission === null) {
            return response()->json(['data' => null], 200);
        }

        return new OnboardingResource($submission);
    }

    /** 본인 기입 정보 저장 (편집 가능한 제출물이 없으면 새 draft 생성) */
    public function store(Request $request): OnboardingResource
    {
        $data = $request->validate([
            'payload' => ['required', 'array'],
            // 전자서명: base64(data URL 또는 순수 base64) PNG. 서명 캔버스 결과.
            'signature' => ['nullable', 'string'],
        ]);

        /** @var Worker $worker */
        $worker = $request->user();

        $submission = $this->latestFor($request);

        // 편집 불가 상태이거나 없으면 새 draft 를 만든다
        if ($submission === null || ! $submission->status->isEditableByWorker()) {
            $submission = new OnboardingSubmission(['status' => OnboardingStatus::Draft]);
            $submission->worker_id = $worker->id;
        }

        $submission->payload = $data['payload'];

        // 전자서명 저장 — private 디스크에 PNG 로 저장하고 경로만 보관(§9)
        if (! empty($data['signature'])) {
            $binary = $this->decodeSignature($data['signature']);
            if ($binary !== null) {
                $path = 'onboarding/signatures/'.$worker->id.'_'.Str::uuid()->toString().'.png';
                Storage::disk('local')->put($path, $binary);
                $submission->signature_path = $path;
            }
        }

        $submission->save();

        return new OnboardingResource($submission->refresh());
    }

    /** base64(data URL 허용) → 바이너리. 유효하지 않으면 null. */
    private function decodeSignature(string $raw): ?string
    {
        if (str_contains($raw, ',')) {
            $raw = substr($raw, strpos($raw, ',') + 1);
        }
        $binary = base64_decode(strtr($raw, ' ', '+'), true);

        return $binary === false ? null : $binary;
    }

    /** 제출 */
    public function submit(Request $request, SubmitOnboardingAction $action): OnboardingResource|JsonResponse
    {
        $submission = $this->latestFor($request);

        if ($submission === null) {
            return response()->json(['message' => '제출할 온보딩 정보가 없습니다.'], 422);
        }

        try {
            $action->execute($submission);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OnboardingResource($submission->refresh());
    }

    private function latestFor(Request $request): ?OnboardingSubmission
    {
        /** @var Worker $worker */
        $worker = $request->user();

        return OnboardingSubmission::where('worker_id', $worker->id)->latest('id')->first();
    }
}
