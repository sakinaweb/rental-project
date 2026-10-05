<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Firebase Cloud Messaging HTTP v1 (https://firebase.google.com/docs/cloud-messaging/send-message).
 * Authenticates with a service-account JSON (FCM_CREDENTIALS path): a signed JWT is exchanged for an OAuth token.
 */
class FcmPushDriver implements PushDriver
{
    /** @param  array{project_id: string, client_email: string, private_key: string, token_uri?: string}  $credentials */
    public function __construct(private array $credentials) {}

    public function send(string $token, string $title, string $body, array $data = []): bool
    {
        $response = Http::withToken($this->accessToken())->post(
            "https://fcm.googleapis.com/v1/projects/{$this->credentials['project_id']}/messages:send",
            ['message' => ['token' => $token, 'notification' => ['title' => $title, 'body' => $body], 'data' => $data ?: new \stdClass]],
        );

        // 404 UNREGISTERED / 400 INVALID_ARGUMENT: the app was uninstalled or the token is stale.
        if (in_array($response->status(), [400, 404], true)) {
            return false;
        }
        $response->throw();

        return true;
    }

    private function accessToken(): string
    {
        $tokenUri = $this->credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';

        return Cache::remember('fcm-access-token:'.$this->credentials['client_email'], 3000, function () use ($tokenUri) {
            $now = time();
            $jwt = $this->signedJwt([
                'iss' => $this->credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

            return (string) Http::asForm()->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ])->throw()->json('access_token');
        });
    }

    /** @param  array<string, mixed>  $claims */
    private function signedJwt(array $claims): string
    {
        $encode = fn (array $part) => rtrim(strtr(base64_encode((string) json_encode($part)), '+/', '-_'), '=');
        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$encode($claims);

        if (! openssl_sign($unsigned, $signature, $this->credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Could not sign the FCM JWT; check FCM_CREDENTIALS.');
        }

        return $unsigned.'.'.rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }
}
