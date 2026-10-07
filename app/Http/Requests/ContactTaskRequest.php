<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:2000'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'page_url' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'string', 'max:100'],
            'slot_pack' => ['exclude_unless:source,website_slot', 'required', 'string', 'in:base,premium'],
        ];
    }

    public function messages(): array
    {
        return [
            'slot_pack.required' => 'Escolha o pack SLOT: Base ou Premium.',
            'slot_pack.in' => 'Escolha um pack SLOT válido: Base ou Premium.',
        ];
    }
}
