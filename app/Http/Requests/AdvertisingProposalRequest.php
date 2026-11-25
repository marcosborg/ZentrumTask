<?php

namespace App\Http\Requests;

use App\Models\AdvertisingFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdvertisingProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_key' => 'required|uuid',
            'company' => 'required|string|max:120',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:200',
            'phone' => ['required', 'string', 'regex:/^(?=(?:\D*\d){7,15}\D*$)[+0-9 ()-]{7,24}$/'],
            'website' => 'nullable|url:http,https|max:255',
            'sector' => 'required|string|max:100',
            'campaign' => 'required|string|max:160',
            'fleet' => ['required', Rule::in(AdvertisingFormat::FLEET)],
            'regions' => 'required|string|max:200',
            'duration' => ['required', Rule::in(AdvertisingFormat::DURATIONS)],
            'custom_duration' => 'exclude_unless:duration,Personalizada|required|string|max:100',
            'start' => 'nullable|date_format:Y-m-d|after_or_equal:today',
            'budget' => 'nullable|numeric|min:0|max:999999999',
            'format_id' => ['required', 'integer', Rule::exists('advertising_formats', 'id')->where('active', true)],
            'design' => ['required', Rule::in(['Sim', 'Não', 'A definir'])],
            'message' => 'nullable|string|max:3000',
            'privacy' => 'accepted',
            'artwork' => 'nullable|file|mimes:jpg,jpeg,png,pdf|extensions:jpg,jpeg,png,pdf|max:5120',
            'fax' => 'prohibited',
        ];
    }

    public function attributes(): array
    {
        return [
            'company' => 'empresa', 'name' => 'nome do contacto', 'email' => 'email profissional',
            'phone' => 'telefone', 'sector' => 'setor de atividade', 'campaign' => 'tipo de campanha',
            'fleet' => 'número de viaturas', 'regions' => 'regiões', 'duration' => 'duração',
            'custom_duration' => 'duração personalizada', 'format_id' => 'formato publicitário',
            'design' => 'design gráfico', 'artwork' => 'anexo', 'budget' => 'orçamento indicativo',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Preencha o campo «:attribute».',
            'privacy.accepted' => 'Confirme a leitura da política de privacidade.',
            'artwork.max' => 'O ficheiro deve ter até 5 MB.',
            'artwork.extensions' => 'Anexe um documento PDF ou uma imagem JPG ou PNG.',
            'artwork.mimes' => 'Anexe um documento PDF ou uma imagem JPG ou PNG.',
            'phone.regex' => 'Indique um número de telefone válido.',
            'format_id.exists' => 'Selecione um formato disponível.',
            'start.after_or_equal' => 'Escolha uma data a partir de hoje.',
            'email.email' => 'Indique um endereço de email válido.',
        ];
    }
}
