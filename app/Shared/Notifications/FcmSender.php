<?php

declare(strict_types=1);

namespace App\Shared\Notifications;

use App\Shared\Notifications\Models\DeviceToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * FCM HTTP v1 발송기.
 *
 * 서비스 계정으로 서명한 JWT 를 액세스 토큰과 교환해 발송한다. 토큰은 1시간짜리라
 * 캐시에 담아 재사용한다 — 알림마다 OAuth 왕복을 하면 발송이 두 배로 느려진다.
 *
 * **죽은 토큰은 즉시 지운다.** 앱 삭제·재설치로 무효가 된 토큰이 쌓이면 발송할
 * 때마다 실패분을 계속 재시도하게 되고, 나중에는 무엇이 살아 있는 기기인지
 * 알 수 없게 된다. FCM 이 UNREGISTERED/INVALID_ARGUMENT 로 알려줄 때가
 * 유일하게 확실한 정리 시점이다.
 *
 * 키가 설정되지 않은 환경(로컬·테스트)에서는 발송을 건너뛴다. 알림 하나 때문에
 * 가입 승인 같은 본 작업이 실패하면 안 되므로, 이 클래스는 예외를 밖으로
 * 던지지 않는다(설정 오류만 예외).
 */
class FcmSender
{
    private const TOKEN_CACHE_KEY = 'fcm:access_token';

    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /**
     * 읽어 둔 서비스 계정 JSON. false = 아직 안 읽음, null = 없음.
     *
     * 인스턴스 필드로 둔다 — static 으로 두면 테스트 사이에 값이 남아
     * 설정을 바꿔 가며 검증할 수 없다.
     *
     * @var array<string, mixed>|null|false
     */
    private array|null|false $credentials = false;

    /**
     * 여러 기기에 같은 알림을 보낸다.
     *
     * @param  iterable<DeviceToken>  $devices
     * @param  array<string, string>  $data  앱이 탭 이동에 쓰는 값 (문자열만 허용)
     * @return int 발송에 성공한 기기 수
     */
    public function send(
        iterable $devices,
        string $title,
        string $body,
        array $data = [],
        bool $urgent = false,
    ): int {
        if (! $this->isConfigured()) {
            Log::info('FCM 미설정 — 발송 건너뜀', ['title' => $title]);

            return 0;
        }

        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return 0;
        }

        $sent = 0;

        foreach ($devices as $device) {
            if ($this->sendOne($accessToken, $device, $title, $body, $data, $urgent)) {
                $sent++;
            }
        }

        return $sent;
    }

    /** 서비스 계정 키가 놓여 있는지. */
    public function isConfigured(): bool
    {
        return $this->credentials() !== null;
    }

    /** 한 기기에 발송. 성공하면 true. */
    private function sendOne(
        string $accessToken,
        DeviceToken $device,
        string $title,
        string $body,
        array $data,
        bool $urgent,
    ): bool {
        $channel = $urgent
            ? config('fcm.android_channel_urgent')
            : config('fcm.android_channel');

        $payload = [
            'message' => [
                'token' => $device->token,
                'notification' => ['title' => $title, 'body' => $body],
                'android' => [
                    // 긴급은 잠금화면을 깨워야 하므로 high, 나머지는 배터리를 아낀다.
                    'priority' => $urgent ? 'HIGH' : 'NORMAL',
                    'notification' => ['channel_id' => $channel],
                ],
                // FCM data 값은 문자열만 허용한다 — 숫자를 넣으면 400 이 난다.
                'data' => array_map(static fn ($v) => (string) $v, $data),
            ],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->timeout((int) config('fcm.timeout', 10))
                ->post($this->endpoint(), $payload);
        } catch (\Throwable $e) {
            // 네트워크 장애로 본 작업이 실패하면 안 된다.
            Log::warning('FCM 발송 실패(네트워크)', ['error' => $e->getMessage()]);

            return false;
        }

        if ($response->successful()) {
            return true;
        }

        $status = (string) $response->json('error.status', '');

        if ($this->isDeadToken($status)) {
            // 앱 삭제·재설치로 무효가 된 토큰 — 지워야 다음 발송이 깨끗해진다.
            $device->delete();

            Log::info('FCM 무효 토큰 삭제', [
                'token' => DeviceToken::mask($device->token),
                'status' => $status,
            ]);

            return false;
        }

        Log::warning('FCM 발송 실패', [
            'http' => $response->status(),
            'status' => $status,
            'token' => DeviceToken::mask($device->token),
        ]);

        return false;
    }

    /**
     * 다시 보내도 소용없는 토큰인지.
     *
     * UNREGISTERED  — 앱이 삭제됐거나 토큰이 갱신돼 이 값은 죽었다.
     * NOT_FOUND     — 같은 의미의 옛 표기.
     * INVALID_ARGUMENT — 형식 자체가 토큰이 아니다(잘못 저장된 값).
     */
    private function isDeadToken(string $status): bool
    {
        return in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true);
    }

    /** OAuth 액세스 토큰 — 만료 5분 전까지 캐시에서 재사용한다. */
    private function accessToken(): ?string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $credentials = $this->credentials();
        if ($credentials === null) {
            return null;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('fcm.timeout', 10))
                ->post($credentials['token_uri'], [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->signedJwt($credentials),
                ]);
        } catch (\Throwable $e) {
            Log::warning('FCM 토큰 발급 실패(네트워크)', ['error' => $e->getMessage()]);

            return null;
        }

        $token = $response->json('access_token');
        if (! is_string($token) || $token === '') {
            Log::error('FCM 토큰 발급 실패', ['response' => $response->json('error')]);

            return null;
        }

        $expires = (int) $response->json('expires_in', 3600);
        Cache::put(self::TOKEN_CACHE_KEY, $token, max(60, $expires - 300));

        return $token;
    }

    /** 서비스 계정 개인키로 서명한 JWT. */
    private function signedJwt(array $credentials): string
    {
        $now = time();

        $encode = static fn (array $part): string => rtrim(
            strtr(base64_encode(json_encode($part, JSON_UNESCAPED_SLASHES)), '+/', '-_'),
            '=',
        );

        $input = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode([
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $credentials['token_uri'],
            'iat' => $now,
            'exp' => $now + 3600,
        ]);

        if (! openssl_sign($input, $signature, $credentials['private_key'], 'sha256')) {
            throw new RuntimeException('FCM 서비스 계정 개인키로 서명할 수 없습니다.');
        }

        return $input.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function endpoint(): string
    {
        return 'https://fcm.googleapis.com/v1/projects/'.$this->projectId().'/messages:send';
    }

    private function projectId(): string
    {
        return (string) (config('fcm.project_id') ?: $this->credentials()['project_id'] ?? '');
    }

    /**
     * 서비스 계정 JSON. 없거나 깨졌으면 null (발송을 건너뛴다).
     *
     * @return array<string, mixed>|null
     */
    private function credentials(): ?array
    {
        if ($this->credentials !== false) {
            return $this->credentials;
        }

        $path = (string) config('fcm.credentials');
        if ($path === '') {
            return $this->credentials = null;
        }

        // 상대경로는 프로젝트 기준으로 푼다.
        if (! str_starts_with($path, '/') && ! preg_match('/^[A-Za-z]:/', $path)) {
            $path = base_path($path);
        }

        if (! is_readable($path)) {
            return $this->credentials = null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            Log::error('FCM 서비스 계정 파일 형식이 올바르지 않습니다.', ['path' => $path]);

            return $this->credentials = null;
        }

        $data['token_uri'] ??= 'https://oauth2.googleapis.com/token';

        return $this->credentials = $data;
    }
}
