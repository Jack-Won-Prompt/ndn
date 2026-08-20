<?php

declare(strict_types=1);

namespace App\Domains\Recruitment\Http\Controllers\Web;

use App\Domains\Recruitment\Enums\WorkerStatus;
use App\Domains\Recruitment\Models\Worker;
use App\Http\Controllers\Controller;
use App\Shared\Translation\Concerns\RendersInWorkerLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 근로자 웹 로그인·비밀번호 찾기 (업무흐름 §2).
 *
 * 앱은 Sanctum 토큰을 쓰고(§9) 이쪽은 세션 가드(`worker`)를 쓴다. 둘을 한 가드에
 * 섞지 않는 이유는 config/auth.php 주석에 적어 뒀다.
 *
 * 로그인 자격은 앱과 같다 — **합격(활성) 계정만 들어온다.** 승인 대기 상태로
 * 로그인이 되면 아직 결과가 안 났는데 난 것처럼 보인다.
 */
class WorkerAuthController extends Controller
{
    use RendersInWorkerLocale;

    public function showLogin(): Response|RedirectResponse
    {
        // 아직 누구인지 모르므로 방문자가 헤더에서 고른 언어로 보여 준다(§6).
        return Auth::guard('worker')->check()
            ? redirect()->route('worker.home')
            : $this->renderLocalized('site.worker-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $worker = Worker::where('email', $data['email'])->first();

        // 계정이 있는지 없는지가 응답 차이로 새어나가지 않게 같은 오류로 묶는다.
        if ($worker === null || $worker->password === null
            || ! Auth::guard('worker')->validate($data)) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }

        if (! $worker->status->canLogin()) {
            // 승인 대기만 자국어로 안내한다(§6). 나머지는 자격증명 오류로 통일 —
            // 불합격·이탈 여부를 로그인 화면에서 알려 줄 이유가 없다.
            $message = $worker->status === WorkerStatus::Pending
                ? trans('worker.pending_approval', [], $worker->locale ?: 'ko')
                : __('auth.failed');

            throw ValidationException::withMessages(['email' => [$message]]);
        }

        Auth::guard('worker')->login($worker, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('worker.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('worker')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('site.home');
    }

    /* ---------- 비밀번호 찾기 ---------- */

    public function showForgot(): Response
    {
        return $this->renderLocalized('site.worker-forgot');
    }

    /**
     * 재설정 링크 발송.
     *
     * 가입한 주소인지 아닌지를 응답으로 알려 주지 않는다 — 그러면 이 화면이
     * 가입 여부 조회기가 된다. 결과와 상관없이 같은 안내를 돌려준다.
     */
    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::broker('workers')->sendResetLink($request->only('email'));

        return back()->with('status', '입력하신 주소로 가입된 계정이 있으면 재설정 링크를 보냈습니다. 메일함을 확인해 주세요.');
    }

    public function showReset(Request $request, string $token): Response
    {
        return $this->renderLocalized('site.worker-reset', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker('workers')->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (Worker $worker, string $password) {
                $worker->forceFill([
                    'password' => $password,          // hashed cast
                    'remember_token' => Str::random(60),
                ])->save();

                activity('worker-account')
                    ->performedOn($worker)
                    ->log('근로자 비밀번호 재설정');
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return redirect()->route('worker.login')->with('status', '비밀번호를 바꿨습니다. 새 비밀번호로 로그인해 주세요.');
    }
}
