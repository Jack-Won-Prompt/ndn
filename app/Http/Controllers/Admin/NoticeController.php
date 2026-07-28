<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domains\Support\Actions\SendNoticeAction;
use App\Domains\Support\Models\Notice;
use App\Http\Controllers\Controller;
use App\Shared\Support\LocalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * 근로자 공지사항 발송·이력 (관리자 콘솔).
 */
class NoticeController extends Controller
{
    /**
     * 발송 이력(최근순).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rows(): array
    {
        return Notice::query()->latest('id')->limit(200)->get()
            ->map(fn (Notice $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'target' => $n->targetLabel(),
                'count' => $n->recipients_count,
                'sent' => LocalTime::format($n->created_at),
            ])->all();
    }

    /** 공지 발송 */
    public function store(Request $request, SendNoticeAction $action): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:4000'],
            'target' => ['required', Rule::in([Notice::TARGET_ALL, Notice::TARGET_NATIONALITY, Notice::TARGET_STATUS])],
            'target_value' => ['nullable', 'string', 'max:20', 'required_unless:target,'.Notice::TARGET_ALL],
        ]);

        $notice = $action->execute(
            $data['title'],
            $data['body'],
            $data['target'],
            $data['target'] === Notice::TARGET_ALL ? null : ($data['target_value'] ?? null),
            Auth::id(),
        );

        return redirect(url('admin/screen/notices'))
            ->with('notice_sent', $notice->recipients_count);
    }
}
