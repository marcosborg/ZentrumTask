<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatSessionStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => ['nullable', 'uuid'],
            'source' => ['nullable', 'string', 'in:website,app,whatsapp'],
            'external_id' => ['nullable', 'string', 'max:120'],
            'external_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
