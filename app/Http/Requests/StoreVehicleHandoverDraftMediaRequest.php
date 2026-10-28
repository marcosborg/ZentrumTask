<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreVehicleHandoverDraftMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Nao foi possivel guardar o ficheiro.',
            'errors' => $validator->errors(),
        ], 422, ['Access-Control-Allow-Origin' => '*']));
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $mediaRules = $this->input('kind') === 'video'
            ? ['nullable', File::types(['mp4', 'webm', 'mov'])->max(32 * 1024)]
            : ['required', $this->hasFile('media') ? File::image()->max(2 * 1024) : 'string'];

        return [
            'kind' => ['required', Rule::in(['photo', 'video'])],
            'key' => ['required', 'string', 'max:100'],
            'media' => $mediaRules,
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'kind.required' => 'Indica o tipo de ficheiro.',
            'kind.in' => 'Tipo de ficheiro invalido.',
            'key.required' => 'Indica a zona do ficheiro.',
            'media.required' => 'Seleciona uma fotografia.',
            'media.max' => 'O ficheiro excede o limite: 2 MB por fotografia ou 32 MB por video.',
            'media.mimes' => 'O formato do ficheiro nao e suportado.',
            'media.image' => 'Seleciona uma imagem valida.',
            'media.uploaded' => 'O envio falhou. Verifica a ligacao e tenta novamente.',
        ];
    }
}
