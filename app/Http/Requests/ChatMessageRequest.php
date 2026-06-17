<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_token' => ['required', 'uuid'],
            'message' => ['required', 'string', 'min:1', 'max:2000'],
            'source' => ['nullable', 'string', 'in:website,app,whatsapp'],
            'external_id' => ['nullable', 'string', 'max:120'],
            'external_name' => ['nullable', 'string', 'max:120'],
            'external_phone' => ['nullable', 'string', 'max:40'],
        ];
    }
}
