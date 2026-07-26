<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Actions\UpdateTicketStatusAction;
use App\Domains\Support\Enums\TicketStatus;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 민원 wwGrid — 상태만 편집(도메인 Action으로 전이 규칙 준수).
 * 등록/삭제는 하지 않는다(근로자 앱에서 접수).
 */
class TicketGridController extends Controller
{
    public static function rows(): array
    {
        return SupportTicket::with('worker')->latest('id')->limit(2000)->get()
            ->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'worker' => $t->worker?->name ?? '—',
                'type' => $t->type->label(),
                'subject' => $t->subject,
                'body' => $t->body,
                'status' => $t->status->value,
                'created' => $t->created_at?->format('Y-m-d H:i'),
            ])->all();
    }

    /** 변경 저장 — updated 행의 상태 전이만 반영 */
    public function save(Request $request, UpdateTicketStatusAction $action): JsonResponse
    {
        $payload = $request->validate(['updated' => ['array'], 'added' => ['array'], 'deleted' => ['array']]);

        try {
            foreach ($payload['updated'] ?? [] as $u) {
                $cur = $u['current'] ?? [];
                $id = $cur['id'] ?? null;
                $status = $cur['status'] ?? null;
                if (! $id || ! $status) {
                    continue;
                }
                $ticket = SupportTicket::find($id);
                if (! $ticket) {
                    continue;
                }
                $target = TicketStatus::tryFrom($status);
                if (! $target || $ticket->status === $target) {
                    continue;
                }
                $action->execute($ticket, $target, $request->user());
            }
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => '상태를 저장했습니다.', 'rows' => self::rows()]);
    }
}
