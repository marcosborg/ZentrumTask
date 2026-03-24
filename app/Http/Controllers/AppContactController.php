<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactTaskRequest;
use Illuminate\Http\JsonResponse;

class AppContactController extends Controller
{
    public function __invoke(ContactTaskRequest $request): JsonResponse
    {
        $created = WebsiteController::createContactLead(
            $request->validated(),
            'mobile_app_form',
        );

        if (! $created) {
            return $this->jsonResponse([
                'message' => 'Nao foi possivel enviar o pedido de contacto.',
            ], 422);
        }

        return $this->jsonResponse([
            'message' => 'Pedido enviado com sucesso. Sera contactado brevemente.',
        ]);
    }

    protected function jsonResponse(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With, Accept',
        ]);
    }
}
