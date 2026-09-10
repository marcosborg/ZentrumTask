<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreVanReservationRequest extends VanAvailabilityRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'mode' => ['required', Rule::in(['self_drive', 'with_driver'])],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'submission_key' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254'],
            'phone' => ['required', 'string', 'min:6', 'max:40'],
            'purpose' => ['required', Rule::in(['goods', 'moving'])],
            'origin' => ['exclude_unless:mode,with_driver', 'required', 'string', 'max:255'],
            'destination' => ['exclude_unless:mode,with_driver', 'required', 'string', 'max:255'],
            'loading_help' => ['exclude_unless:mode,with_driver', 'sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:3000'],
            'accept_terms' => ['accepted'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'email.email' => 'Indique um email válido.', 'accept_terms.accepted' => 'Confirme que leu as condições.', 'submission_key.uuid' => 'Atualize a página e tente novamente.', 'website.max' => 'Não foi possível enviar o pedido.'];
    }
}
