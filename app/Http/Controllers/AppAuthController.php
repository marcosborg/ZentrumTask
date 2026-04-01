<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AppAuthController extends AppApiController
{
    public function login(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'email' => ['required', 'email'],
                'password' => ['required', 'string'],
            ], [
                'email.required' => 'O email e obrigatorio.',
                'email.email' => 'Indica um email valido.',
                'password.required' => 'A password e obrigatoria.',
            ]);
        } catch (ValidationException $exception) {
            return $this->corsJson([
                'message' => 'Nao foi possivel validar o login.',
                'errors' => $exception->errors(),
            ], 422);
        }

        $user = User::query()
            ->where('email', $credentials['email'])
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->corsJson([
                'message' => 'Credenciais invalidas.',
                'errors' => [
                    'email' => ['Credenciais invalidas.'],
                ],
            ], 422);
        }

        return $this->corsJson([
            'authenticated' => true,
            'token' => $this->issueAppToken($user),
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->revokeAppToken($request->bearerToken());

        return $this->corsJson([
            'logged_out' => true,
        ]);
    }
}
