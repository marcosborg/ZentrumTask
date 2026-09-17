<?php

namespace App\Services;

use App\Models\CandidateApplication;

class IfthenpayMultibancoService
{
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
}
