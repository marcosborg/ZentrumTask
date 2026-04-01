<?php

namespace App\Http\Controllers;

use App\Models\AppDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppDeviceTokenController extends AppApiController
{
    public function store(Request $request): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        try {
            $data = $request->validate([
                'token' => ['required', 'string', 'max:4096'],
                'platform' => ['required', 'string', 'in:android'],
                'device_name' => ['nullable', 'string', 'max:255'],
            ]);
        } catch (ValidationException $exception) {
            return $this->corsJson([
                'message' => 'Nao foi possivel validar o dispositivo.',
                'errors' => $exception->errors(),
            ], 422);
        }

        AppDeviceToken::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $data['token'])],
            [
                'user_id' => $user->id,
                'token' => $data['token'],
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return $this->corsJson([
            'message' => 'Dispositivo registado.',
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $this->resolveAppUser($request);

        if (! $user) {
            return $this->corsJson([
                'message' => 'Sessao invalida.',
            ], 401);
        }

        try {
            $data = $request->validate([
                'token' => ['required', 'string', 'max:4096'],
            ]);
        } catch (ValidationException $exception) {
            return $this->corsJson([
                'message' => 'Nao foi possivel validar o dispositivo.',
                'errors' => $exception->errors(),
            ], 422);
        }

        AppDeviceToken::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $data['token']))
            ->delete();

        return $this->corsJson([
            'message' => 'Dispositivo removido.',
        ]);
    }
}
