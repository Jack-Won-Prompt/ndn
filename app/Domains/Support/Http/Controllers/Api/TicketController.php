<?php

declare(strict_types=1);

namespace App\Domains\Support\Http\Controllers\Api;

use App\Domains\Recruitment\Models\Worker;
use App\Domains\Support\Actions\CreateSupportTicketAction;
use App\Domains\Support\Enums\TicketType;
use App\Domains\Support\Http\Requests\StoreTicketRequest;
use App\Domains\Support\Models\SupportTicket;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 근로자 앱 — 민원 (CLAUDE.md §9, 업무흐름 §8).
 * 인증된 Worker 본인 민원만 접수·조회한다(스코프 자동 보장).
 */
class TicketController extends Controller
{
    /** 본인 민원 목록 */
    public function index(Request $request): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $tickets = SupportTicket::where('worker_id', $worker->id)
            ->latest('id')
            ->get()
            ->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'type' => $t->type->value,
                'subject' => $t->subject,
                'status' => $t->status->value,
                'created' => $t->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $tickets]);
    }

    /** 민원 접수 */
    public function store(StoreTicketRequest $request, CreateSupportTicketAction $action): JsonResponse
    {
        /** @var Worker $worker */
        $worker = $request->user();

        $ticket = $action->execute(
            $worker,
            TicketType::from($request->string('type')->value()),
            $request->string('subject')->value(),
            $request->input('body'),
        );

        return response()->json([
            'data' => [
                'id' => $ticket->id,
                'type' => $ticket->type->value,
                'status' => $ticket->status->value,
            ],
        ], 201);
    }
}
