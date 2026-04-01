<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

abstract class AppApiController extends Controller
{
    protected function corsJson(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->withHeaders([
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
                'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With, Accept',
            ]);
    }

    protected function resolveAppUser(Request $request): ?User
    {
        $token = $request->bearerToken();

        if (! $token) {
            return null;
        }

        $userId = Cache::get($this->tokenCacheKey($token));

        if (! $userId) {
            return null;
        }

        return User::query()->find($userId);
    }

    protected function issueAppToken(User $user): string
    {
        $token = bin2hex(random_bytes(32));

        Cache::put($this->tokenCacheKey($token), $user->getKey(), now()->addDays(30));

        return $token;
    }

    protected function revokeAppToken(?string $token): void
    {
        if (! $token) {
            return;
        }

        Cache::forget($this->tokenCacheKey($token));
    }

    protected function tokenCacheKey(string $token): string
    {
        return 'app_auth_token:'.$token;
    }
}
