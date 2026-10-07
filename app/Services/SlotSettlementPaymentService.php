<?php

namespace App\Services;

class SlotSettlementPaymentService
{
    public function pay(\App\Models\DriverSettlement $settlement, float $amount, ?string $paymentReference = null): void
    {
        abort_unless(config('slots.enabled'), 403);
        $paymentReference ??= (string) \Illuminate\Support\Str::uuid();
        \Illuminate\Support\Facades\DB::transaction(function () use ($settlement, $amount, $paymentReference): void {
            \App\Models\Driver::query()->whereKey($settlement->driver_id)->lockForUpdate()->firstOrFail();
            \App\Models\DriverParticipation::query()->whereKey($settlement->driver_participation_id)->lockForUpdate()->firstOrFail();
            $settlement = \App\Models\DriverSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();
            $existing = \App\Models\DriverBalanceMovement::query()->where('payment_reference', $paymentReference)->first();
            if ($existing) {
                if ((int) $existing->driver_settlement_id !== (int) $settlement->id || round((float) $existing->amount, 2) !== -round($amount, 2)) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'A confirmação já foi utilizada para outro pagamento.']);
                }

                return;
            }
            $due = round((float) $settlement->amount_payable - (float) $settlement->amount_transferred, 2);
            if ($settlement->operation !== 'slot' || $settlement->is_paid || ! $settlement->platform_received_at || ! $settlement->reconciled_at || ($settlement->payment_due_at && $settlement->payment_due_at->toDateString() > now(config('slots.timezone'))->toDateString()) || $amount <= 0 || round($amount, 2) > max(0, $due)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'Confirme recebimento e reconciliação e indique um valor pendente positivo deste settlement.']);
            }
            $balance = \App\Models\DriverBalance::query()->where('driver_participation_id', $settlement->driver_participation_id)->lockForUpdate()->firstOrFail();
            if ($amount > max(0, (float) $balance->current_balance)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['amount' => 'O pagamento excede o saldo disponível da participação.']);
            }
            $amount = round($amount, 2);
            $paid = round((float) $settlement->amount_transferred + $amount, 2);
            $settlement->update(['amount_transferred' => $paid, 'is_paid' => $paid >= (float) $settlement->amount_payable, 'paid_at' => now(), 'amount_due' => round((float) $settlement->amount_due - $amount, 2)]);
            $balance->update(['current_balance' => round((float) $balance->current_balance - $amount, 2), 'is_settled' => round((float) $balance->current_balance - $amount, 2) === 0.0]);
            \App\Models\DriverBalanceMovement::query()->create(['payment_reference' => $paymentReference, 'driver_id' => $settlement->driver_id, 'driver_participation_id' => $settlement->driver_participation_id, 'operation' => 'slot', 'driver_balance_id' => $balance->id, 'driver_settlement_id' => $settlement->id, 'amount' => -$amount, 'type' => 'payment', 'description' => 'Pagamento SLOT confirmado manualmente']);
        });
    }
}
