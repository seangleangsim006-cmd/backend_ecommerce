<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FcmService
{
    public function sendToUser(User $user, string $title, string $body, array $data = []): void
    {
        $tokens = $user->fcmTokens()->pluck('token');

        foreach ($tokens as $token) {
            $this->send($token, $title, $body, $data);
        }
    }

    private function send(string $token, string $title, string $body, array $data): void
    {
        $credentialsPath = config('services.firebase.credentials');

        if (!$credentialsPath || !is_file($credentialsPath)) {
            Log::warning('FCM skipped: Firebase credentials file is not configured.');
            return;
        }

        try {
            $credentials = json_decode((string) file_get_contents($credentialsPath), true, 512, JSON_THROW_ON_ERROR);
            $accessToken = Cache::remember('firebase.access_token', now()->addMinutes(50), function () use ($credentials): string {
                return $this->createAccessToken($credentials);
            });

            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->post('https://fcm.googleapis.com/v1/projects/' . $credentials['project_id'] . '/messages:send', [
                    'message' => [
                        'token' => $token,
                        'notification' => [
                            'title' => $title,
                            'body' => $body,
                        ],
                        'data' => collect($data)->map(fn ($value): string => (string) $value)->all(),
                    ],
                ]);

            if ($response->status() === 404 || str_contains($response->body(), 'UNREGISTERED')) {
                \App\Models\FcmToken::where('token', $token)->delete();
            } elseif ($response->failed()) {
                Log::warning('FCM delivery failed.', ['status' => $response->status(), 'response' => $response->json()]);
            }
        } catch (\Throwable $exception) {
            Log::error('FCM delivery error.', ['message' => $exception->getMessage()]);
        }
    }

    private function createAccessToken(array $credentials): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => time(),
            'exp' => time() + 3600,
        ], JSON_THROW_ON_ERROR));

        openssl_sign($header . '.' . $payload, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        $assertion = $header . '.' . $payload . '.' . $this->base64UrlEncode($signature);

        return (string) Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ])->throw()->json('access_token');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}