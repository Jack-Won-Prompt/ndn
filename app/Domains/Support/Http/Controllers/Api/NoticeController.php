<?php

declare(strict_types=1);

namespace App\Domains\Support\Http\Controllers\Api;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Models\Notice;
use App\Shared\Translation\GoogleTranslator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * 근로자 앱 — 공지사항 목록 (본인이 대상인 공지, 근로자 언어로 번역).
 */
class NoticeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();
        $locale = $worker->locale ?: 'ko';

        $notices = Notice::query()
            ->where(function ($q) use ($worker) {
                $q->where('target', Notice::TARGET_ALL)
                    ->orWhere(fn ($w) => $w->where('target', Notice::TARGET_NATIONALITY)->where('target_value', $worker->nationality))
                    ->orWhere(fn ($w) => $w->where('target', Notice::TARGET_STATUS)->where('target_value', $worker->status?->value));
            })
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(fn (Notice $n) => [
                'id' => $n->id,
                'title' => $this->tr($n->title, $locale),
                'body' => $this->tr($n->body, $locale),
                'sent_at' => $n->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $notices]);
    }

    private function tr(string $text, string $locale): string
    {
        return $locale === 'ko' ? $text : GoogleTranslator::translate($text, $locale, 'ko');
    }
}
