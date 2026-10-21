<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Cache;

class RegisterAppDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = $this->bearerToken();
        $userId = $token ? Cache::get('app_auth_token:'.$token) : null;

        return $userId && User::query()->whereKey($userId)->exists();
    }

    /** @return array<string, array<string>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', 'string', 'in:android,ios'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'token.required' => 'O token de notificacoes e obrigatorio.',
            'token.string' => 'O token de notificacoes deve ser texto.',
            'token.max' => 'O token de notificacoes e demasiado longo.',
            'platform.required' => 'A plataforma e obrigatoria.',
            'platform.string' => 'A plataforma deve ser texto.',
            'platform.in' => 'A plataforma deve ser Android ou iOS.',
            'device_name.string' => 'O nome do dispositivo deve ser texto.',
            'device_name.max' => 'O nome do dispositivo e demasiado longo.',
        ];
    }

    protected function failedAuthorization(): void
    {
        $this->reject(['message' => 'Sessao invalida.'], 401);
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->reject([
            'message' => 'Nao foi possivel validar o dispositivo.',
            'errors' => $validator->errors()->toArray(),
        ], 422);
    }

    /** @param array<string, mixed> $payload */
    private function reject(array $payload, int $status): never
    {
        throw new HttpResponseException(response()->json($payload, $status)->withHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Authorization, Content-Type, X-Requested-With, Accept',
        ]));
    }
}
