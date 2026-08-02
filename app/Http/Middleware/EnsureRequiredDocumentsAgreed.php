<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Onboarding\Models\RequiredDocument;
use App\Domains\Recruitment\Models\Worker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 필수 문서 동의 게이트 — 미동의 상태로는 앱의 다른 화면으로 넘어가지 못하게 막는다.
 *
 * 근로자 의무사항·표준근로계약서·상해보험 약정서 등에 모두 동의해야 통과한다.
 * 막을 때 409 와 함께 남은 문서 목록을 내려주므로, 앱은 그 응답을 보고 동의 화면을
 * 강제로 띄우면 된다. 동의 화면 자체(GET/POST required-documents)와 로그아웃은
 * 이 미들웨어 밖에 둬야 잠기지 않는다.
 */
class EnsureRequiredDocumentsAgreed
{
    public function handle(Request $request, Closure $next): Response
    {
        $worker = $request->user();

        if (! $worker instanceof Worker) {
            return $next($request);
        }

        $pending = RequiredDocument::pendingFor($worker);

        if ($pending->isEmpty()) {
            return $next($request);
        }

        $locale = $worker->locale ?: 'ko';

        return response()->json([
            'message' => trans('worker.documents_required', [], $locale),
            'meta' => [
                'reason' => 'required_documents_pending',
                'pending' => $pending->map(fn (RequiredDocument $d) => [
                    'id' => $d->id,
                    'code' => $d->code,
                    'title' => $d->title($locale),
                    'version' => $d->version,
                ])->all(),
            ],
        ], 409);
    }
}
