<?php

namespace App\Services;

use App\Models\AppDeviceToken;
use App\Models\Task;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class AndroidPushNotificationService
{
    public function sendNewContactTask(Task $task): void
    {
        $credentials = $this->loadCredentials();

        if ($credentials === null) {
            return;
        }

        $tokens = $this->resolveTokens($task);

        if ($tokens->isEmpty()) {
            return;
        }

        try {
            $accessToken = $this->issueAccessToken($credentials);
            $projectId = (string) Arr::get($credentials, 'project_id', config('services.fcm.project_id', ''));

            if ($accessToken === '' || $projectId === '') {
                return;
            }

            $invalidTokenHashes = collect();

            foreach ($tokens as $deviceToken) {
                $response = Http::timeout(10)
                    ->withToken($accessToken)
                    ->post(sprintf('https://fcm.googleapis.com/v1/projects/%s/messages:send', $projectId), [
                        'message' => $this->buildMessagePayload($task, $deviceToken->token),
                    ]);

                if ($response->successful()) {
                    continue;
                }

                $errorStatus = (string) data_get($response->json(), 'error.status', '');
                $errorMessage = (string) data_get($response->json(), 'error.message', '');

                Log::warning('FCM push rejected.', [
                    'task_id' => $task->id,
                    'device_token_hash' => $deviceToken->token_hash,
                    'http_status' => $response->status(),
                    'error_status' => $errorStatus,
                    'error_message' => $errorMessage,
                    'response_body' => $response->json() ?: $response->body(),
                ]);

                if (
                    in_array($response->status(), [400, 404], true)
                    && ($errorStatus === 'INVALID_ARGUMENT' || $errorStatus === 'NOT_FOUND' || str_contains($errorMessage, 'UNREGISTERED'))
                ) {
                    $invalidTokenHashes->push($deviceToken->token_hash);
                }
            }

            if ($invalidTokenHashes->isNotEmpty()) {
                AppDeviceToken::query()->whereIn('token_hash', $invalidTokenHashes->all())->delete();
            }
        } catch (Throwable $exception) {
            Log::warning('FCM push failed.', [
                'task_id' => $task->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function loadCredentials(): ?array
    {
        $relativePath = (string) config('services.fcm.service_account');

        if ($relativePath === '') {
            return null;
        }

        $resolvedPath = $this->resolveCredentialsPath($relativePath);

        if ($resolvedPath === null) {
            return null;
        }

        $decoded = json_decode(Storage::disk('local')->get($resolvedPath), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function resolveCredentialsPath(string $path): ?string
    {
        $candidates = collect([$path]);

        if (Str::startsWith($path, 'private/')) {
            $candidates->push(Str::after($path, 'private/'));
        } else {
            $candidates->push('private/'.$path);
        }

        return $candidates
            ->map(fn (string $candidate) => trim($candidate, '/'))
            ->first(fn (string $candidate) => Storage::disk('local')->exists($candidate));
    }

    protected function issueAccessToken(array $credentials): string
    {
        $privateKey = (string) ($credentials['private_key'] ?? '');
        $clientEmail = (string) ($credentials['client_email'] ?? '');
        $tokenUri = (string) ($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token');

        if ($privateKey === '' || $clientEmail === '') {
            return '';
        }

        $now = time();
        $unsignedJwt = implode('.', [
            $this->base64UrlEncode(json_encode([
                'alg' => 'RS256',
                'typ' => 'JWT',
            ])),
            $this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => $tokenUri,
                'exp' => $now + 3600,
                'iat' => $now,
            ])),
        ]);

        $signature = '';
        $signed = openssl_sign($unsignedJwt, $signature, $privateKey, 'sha256WithRSAEncryption');

        if (! $signed) {
            return '';
        }

        $assertion = $unsignedJwt.'.'.$this->base64UrlEncode($signature);
        $response = Http::asForm()
            ->timeout(10)
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            return '';
        }

        return (string) $response->json('access_token', '');
    }

    protected function buildMessagePayload(Task $task, string $deviceToken): array
    {
        return [
            'token' => $deviceToken,
            'notification' => [
                'title' => 'Novo contacto',
                'body' => $this->buildBody($task),
            ],
            'data' => [
                'type' => 'kanban_task',
                'task_id' => (string) $task->id,
                'route' => '/reserved/tasks?task='.$task->id,
                'phone' => (string) data_get($task->meta ?? [], 'phone', ''),
                'contact_name' => (string) data_get($task->meta ?? [], 'contact_name', ''),
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => (string) config('services.fcm.android_channel_id', 'new-contacts'),
                    'click_action' => 'OPEN_RESERVED_TASK',
                    'sound' => 'default',
                ],
            ],
        ];
    }

    protected function resolveTokens(Task $task): Collection
    {
        $assignedTokens = AppDeviceToken::query()
            ->where('platform', 'android')
            ->when(
                $task->assigned_to_id,
                fn ($query) => $query->where('user_id', $task->assigned_to_id)
            )
            ->orderByDesc('last_used_at')
            ->get();

        if ($task->assigned_to_id && $assignedTokens->isNotEmpty()) {
            return $assignedTokens->values();
        }

        return AppDeviceToken::query()
            ->where('platform', 'android')
            ->orderByDesc('last_used_at')
            ->get()
            ->unique('token_hash')
            ->values();
    }

    protected function buildBody(Task $task): string
    {
        $contactName = trim((string) data_get($task->meta ?? [], 'contact_name', ''));
        $phone = trim((string) data_get($task->meta ?? [], 'phone', ''));

        if ($contactName !== '' && $phone !== '') {
            return $contactName.' · '.$phone;
        }

        if ($contactName !== '') {
            return $contactName;
        }

        if ($phone !== '') {
            return $phone;
        }

        return $task->title;
    }

    protected function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
