<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use App\Repositories\Contracts\DeviceTokenRepositoryInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging push delivery — ARCHITECTURE.md's "no
 * third-party SDK" convention applies here exactly as it does to
 * AiService: rather than pulling in kreait/firebase-php, this implements
 * the RFC 7523 JWT Bearer grant for a Google service account by hand
 * (signing with PHP's built-in openssl_sign) to get a short-lived OAuth2
 * access token, then calls the FCM HTTP v1 REST API directly via
 * Laravel's Http client.
 *
 * Flow:
 *   1. Load the service account JSON (services.firebase.credentials).
 *   2. Build + RS256-sign a JWT asserting the service account as issuer,
 *      scoped to firebase.messaging.
 *   3. Exchange that JWT for an access token at Google's token endpoint
 *      (cached ~50 min, since tokens are valid for 60).
 *   4. POST to fcm.googleapis.com/v1/projects/{id}/messages:send per
 *      device token, bearer-authenticated with that access token.
 */
class FcmService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';
    private const CACHE_KEY = 'fcm:access_token';

    public function __construct(private readonly DeviceTokenRepositoryInterface $deviceTokens) {}

    /**
     * Sends to every device token registered for the user, pruning any
     * token FCM itself reports as no-longer-valid (app uninstalled,
     * token rotated without us hearing about it, etc).
     *
     * @param array<string, mixed> $data
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        foreach ($this->deviceTokens->forUser($user) as $deviceToken) {
            $this->sendToToken($deviceToken, $title, $body, $data);
        }
    }

    private function sendToToken(DeviceToken $deviceToken, string $title, string $body, array $data): void
    {
        $projectId = config('services.firebase.project_id');

        if (! $projectId) {
            Log::warning('FCM: FIREBASE_PROJECT_ID is not configured; skipping push send.');

            return;
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (\Throwable $e) {
            Log::error('FCM: could not obtain an access token.', ['error' => $e->getMessage()]);

            return;
        }

        $response = Http::withToken($accessToken)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $deviceToken->token,
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    // FCM's data payload requires every value to be a string.
                    'data' => array_map('strval', $data),
                ],
            ]);

        if ($response->successful()) {
            $deviceToken->forceFill(['last_used_at' => now()])->save();

            return;
        }

        $errorStatus = $response->json('error.status');

        if (in_array($errorStatus, ['NOT_FOUND', 'UNREGISTERED', 'INVALID_ARGUMENT'], true)) {
            // Dead registration token — stop retrying it rather than fail
            // forever on every future notification to this user.
            $this->deviceTokens->forceDeleteByToken($deviceToken->token);

            return;
        }

        Log::warning('FCM send failed.', [
            'status' => $response->status(),
            'body' => $response->json(),
        ]);
    }

    /**
     * Exchanges a self-signed JWT for a short-lived OAuth2 access token,
     * cached until shortly before it actually expires (tokens are valid
     * for 3600s per Google's spec).
     */
    private function getAccessToken(): string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(50), function () {
            $credentials = $this->loadServiceAccount();
            $jwt = $this->buildSignedJwt($credentials);

            $response = Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])->throw();

            $accessToken = $response->json('access_token');

            if (! is_string($accessToken) || $accessToken === '') {
                throw new RuntimeException('Google token endpoint did not return an access_token.');
            }

            return $accessToken;
        });
    }

    /**
     * @return array{client_email: string, private_key: string}
     */
    private function loadServiceAccount(): array
    {
        $path = config('services.firebase.credentials');

        if (! $path || ! is_readable($path)) {
            throw new RuntimeException("Firebase service account file not found or unreadable at [{$path}].");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || empty($decoded['client_email']) || empty($decoded['private_key'])) {
            throw new RuntimeException('Firebase service account file is missing client_email/private_key.');
        }

        return [
            'client_email' => $decoded['client_email'],
            'private_key' => $decoded['private_key'],
        ];
    }

    /**
     * Builds and RS256-signs the RFC 7523 JWT bearer assertion by hand —
     * this is the "PHP's built-in openssl" piece of the no-SDK convention.
     *
     * @param array{client_email: string, private_key: string} $credentials
     */
    private function buildSignedJwt(array $credentials): string
    {
        $now = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $segments = [
            $this->base64UrlEncode((string) json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode((string) json_encode($claims, JSON_UNESCAPED_SLASHES)),
        ];

        $signingInput = implode('.', $segments);

        $privateKey = openssl_pkey_get_private($credentials['private_key']);

        if ($privateKey === false) {
            throw new RuntimeException('Could not parse the Firebase service account private key.');
        }

        $signature = '';
        $signed = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (! $signed) {
            throw new RuntimeException('Failed to RS256-sign the FCM service-account JWT.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
