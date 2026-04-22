<?php

namespace App\Services;

use App\Models\CandidateApplication;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IfthenpayMultibancoService
{
    public function getReferenceData(CandidateApplication $application): array
    {
        $baseAmount = $this->baseAmount();
        $vatRate = $this->vatRate();
        $totalAmount = $this->totalAmount();

        return [
            'provider' => 'ifthenpay',
            'status' => $application->reservation_payment_status ?: 'pending_generation',
            'entity' => (string) config('services.ifthenpay.entity'),
            'sub_entity' => $this->normalizedSubEntity(),
            'reference' => $application->reservation_payment_reference,
            'request_id' => $application->reservation_payment_request_id,
            'order_id' => $application->reservation_payment_order_id,
            'message' => $this->messageFor($application),
            'base_amount' => $baseAmount,
            'vat_rate' => $vatRate,
            'vat_amount' => round($totalAmount - $baseAmount, 2),
            'amount' => $totalAmount,
            'formatted_base_amount' => $this->formatCurrency($baseAmount),
            'formatted_vat_amount' => $this->formatCurrency(round($totalAmount - $baseAmount, 2)),
            'formatted_amount' => $this->formatCurrency($totalAmount),
            'generated_at' => $application->reservation_payment_generated_at?->toIso8601String(),
            'expires_at' => $application->reservation_payment_expires_at?->toIso8601String(),
            'is_configured' => $this->isConfigured(),
            'is_paid' => $application->reservation_payment_paid_at !== null,
        ];
    }

    public function ensureReference(CandidateApplication $application): array
    {
        if ($application->reservation_payment_reference && $application->reservation_payment_amount) {
            return $this->getReferenceData($application);
        }

        if (! $this->isConfigured()) {
            $application->forceFill([
                'reservation_payment_provider' => 'ifthenpay',
                'reservation_payment_status' => 'pending_configuration',
                'reservation_payment_entity' => (string) config('services.ifthenpay.entity'),
                'reservation_payment_sub_entity' => $this->normalizedSubEntity(),
                'reservation_payment_base_amount' => $this->baseAmount(),
                'reservation_payment_vat_rate' => $this->vatRate(),
                'reservation_payment_amount' => $this->totalAmount(),
                'reservation_payment_payload' => [
                    'error' => 'missing_mb_key',
                ],
            ])->save();

            return $this->getReferenceData($application);
        }

        $orderId = $application->reservation_payment_order_id ?: $this->makeOrderId($application);

        $payload = array_filter([
            'mbKey' => config('services.ifthenpay.mb_key'),
            'orderId' => $orderId,
            'amount' => number_format($this->totalAmount(), 2, '.', ''),
            'description' => Str::limit('Reserva viatura '.$application->id, 200, ''),
            'clientName' => $application->full_name,
            'clientEmail' => $application->email,
            'clientPhone' => $this->sanitizePhone($application->phone),
            'expiryDays' => config('services.ifthenpay.expiry_days'),
        ], fn ($value) => $value !== null && $value !== '');

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(15)
                ->post($this->endpoint(), $payload)
                ->throw()
                ->json();
        } catch (RequestException $exception) {
            $application->forceFill([
                'reservation_payment_provider' => 'ifthenpay',
                'reservation_payment_status' => 'generation_failed',
                'reservation_payment_order_id' => $orderId,
                'reservation_payment_entity' => (string) config('services.ifthenpay.entity'),
                'reservation_payment_sub_entity' => $this->normalizedSubEntity(),
                'reservation_payment_base_amount' => $this->baseAmount(),
                'reservation_payment_vat_rate' => $this->vatRate(),
                'reservation_payment_amount' => $this->totalAmount(),
                'reservation_payment_payload' => [
                    'request' => $payload,
                    'error' => $exception->response?->json() ?: $exception->getMessage(),
                ],
            ])->save();

            throw $exception;
        }

        $status = (string) ($response['Status'] ?? '');
        $isSuccess = $status === '0';

        $application->forceFill([
            'reservation_payment_provider' => 'ifthenpay',
            'reservation_payment_status' => $isSuccess ? 'generated' : 'generation_failed',
            'reservation_payment_order_id' => $orderId,
            'reservation_payment_entity' => (string) ($response['Entity'] ?? config('services.ifthenpay.entity')),
            'reservation_payment_sub_entity' => $this->normalizedSubEntity(),
            'reservation_payment_reference' => $response['Reference'] ?? null,
            'reservation_payment_request_id' => $response['RequestId'] ?? null,
            'reservation_payment_base_amount' => $this->baseAmount(),
            'reservation_payment_vat_rate' => $this->vatRate(),
            'reservation_payment_amount' => $this->totalAmount(),
            'reservation_payment_generated_at' => now(),
            'reservation_payment_expires_at' => $this->parseExpiryDate($response['ExpiryDate'] ?? null),
            'reservation_payment_payload' => [
                'request' => $payload,
                'response' => $response,
            ],
        ])->save();

        return $this->getReferenceData($application->fresh());
    }

    public function handleCallback(array $data): ?CandidateApplication
    {
        $orderId = $data['orderId'] ?? null;

        if (! is_string($orderId) || $orderId === '') {
            return null;
        }

        $application = CandidateApplication::query()
            ->where('reservation_payment_order_id', $orderId)
            ->first();

        if (! $application) {
            return null;
        }

        $expectedKey = (string) config('services.ifthenpay.anti_phishing_key');

        if ($expectedKey !== '') {
            $incomingKey = (string) ($data['key'] ?? '');

            if (! hash_equals($expectedKey, $incomingKey)) {
                return null;
            }
        }

        $application->forceFill([
            'reservation_payment_status' => 'paid',
            'reservation_payment_paid_at' => now(),
            'reservation_payment_last_checked_at' => now(),
            'reservation_payment_payload' => array_merge($application->reservation_payment_payload ?? [], [
                'callback' => $data,
            ]),
        ])->save();

        return $application;
    }

    public function isConfigured(): bool
    {
        return (string) config('services.ifthenpay.mb_key') !== '';
    }

    public function baseAmount(): float
    {
        return (float) config('services.ifthenpay.initial_deposit_amount', 250);
    }

    public function vatRate(): float
    {
        return (float) config('services.ifthenpay.initial_deposit_vat_rate', 23);
    }

    public function totalAmount(): float
    {
        return round($this->baseAmount() * (1 + ($this->vatRate() / 100)), 2);
    }

    protected function endpoint(): string
    {
        return rtrim((string) config('services.ifthenpay.base_url'), '/')
            .((bool) config('services.ifthenpay.sandbox')
                ? '/multibanco/reference/sandbox'
                : '/multibanco/reference/init');
    }

    protected function makeOrderId(CandidateApplication $application): string
    {
        $prefix = 'RES'.$application->getKey();
        $suffix = Str::upper(Str::random(6));

        return Str::limit($prefix.$suffix, 25, '');
    }

    protected function sanitizePhone(?string $phone): ?string
    {
        if (! is_string($phone) || trim($phone) === '') {
            return null;
        }

        return preg_replace('/[^\d+]/', '', $phone) ?: null;
    }

    protected function parseExpiryDate(mixed $value): ?CarbonInterface
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value) === 1) {
                return Carbon::createFromFormat('d-m-Y', $value)->endOfDay();
            }

            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function formatCurrency(float $amount): string
    {
        return number_format($amount, 2, ',', '.').'€';
    }

    protected function normalizedSubEntity(): string
    {
        return str_pad((string) config('services.ifthenpay.sub_entity'), 3, '0', STR_PAD_LEFT);
    }

    protected function messageFor(CandidateApplication $application): string
    {
        return match ($application->reservation_payment_status) {
            'generated' => 'Referência Multibanco gerada com sucesso.',
            'paid' => 'Pagamento recebido com sucesso.',
            'generation_failed' => 'Não foi possível gerar a referência Multibanco. Tente novamente dentro de instantes.',
            'pending_configuration' => 'A referência será gerada assim que concluirmos a configuração da IfthenPay.',
            default => 'Estamos a preparar a referência Multibanco para esta reserva.',
        };
    }
}
