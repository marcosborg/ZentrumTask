<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VanAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $van = $this->route('van');
        abort_if($van instanceof \App\Models\RentalVan && $van->status !== 'published', 404);

        return true;
    }

    public function rules(): array
    {
        $quote = $this->routeIs('van-rentals.quote');

        return [
            'mode' => [$quote ? 'required' : 'nullable', Rule::in(['self_drive', 'with_driver'])],
            'starts_at' => [$quote ? 'required' : 'nullable', 'required_with:ends_at', 'date_format:Y-m-d\TH:i'],
            'ends_at' => [$quote ? 'required' : 'nullable', 'required_with:starts_at', 'date_format:Y-m-d\TH:i'],
            'month' => ['nullable', 'date_format:Y-m'],
            'capacity' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return ['required' => 'Preencha este campo.', 'required_with' => 'Indique início e fim.', 'date_format' => 'Indique uma data e hora válida.', 'in' => 'Selecione uma modalidade disponível.', 'numeric' => 'Indique um número válido.', 'min' => 'O valor é inferior ao mínimo.', 'max' => 'O valor excede o limite.'];
    }
}
