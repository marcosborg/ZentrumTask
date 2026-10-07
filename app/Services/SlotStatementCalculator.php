<?php

namespace App\Services;

class SlotStatementCalculator
{
    /** @return array<string, mixed> */
    public function components(\App\Models\DriverParticipation $participation, \App\Models\DriverBillingProfile $profile, string $start, string $end, float $net, float $tips, float $expenses = 0): array
    {
        $weekStart = \Illuminate\Support\Carbon::parse($start);
        if ($participation->status === 'preparing' || $participation->starts_at->toDateString() > $end || ($participation->ends_at && $participation->ends_at->toDateString() <= $start)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['period_start' => 'A semana não pertence à participação ativada.']);
        }
        if (! $weekStart->isMonday() || $weekStart->copy()->addDays(6)->toDateString() !== $end || $profile->driver_participation_id !== $participation->id || $participation->operation !== \App\Enums\TvdeOperation::Slot) {
            throw \Illuminate\Validation\ValidationException::withMessages(['period_start' => 'Selecione a semana e o perfil fiscal da participação SLOT.']);
        }
        $pack = app(ParticipationService::class)->packFor($participation, max($start, $participation->starts_at->toDateString()));
        $effectiveStart = max($start, $participation->starts_at->toDateString());
        $effectiveEnd = $participation->ends_at ? min($end, $participation->ends_at->copy()->subDay()->toDateString()) : $end;
        if (($profile->valid_from && $profile->valid_from->toDateString() > $effectiveStart) || ($profile->valid_to && $profile->valid_to->toDateString() < $effectiveEnd)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['billing_profile_id' => 'O perfil fiscal deve cobrir todos os dias da participação nesta semana.']);
        }
        if (! $pack) {
            throw \Illuminate\Validation\ValidationException::withMessages(['slot_pack_id' => 'A participação não tem pack para esta semana.']);
        }
        $fee = $participation->activeDuring($start, $end) ? (float) $pack->weekly_price : 0;
        $vat = $profile->vat_refund_mode === \App\Enums\VatRefundMode::DriverDeliversVat ? round($net * (float) $profile->vat_percent / 100, 2) : 0;
        $withholding = $profile->apply_withholding_tax ? round($net * (float) $profile->withholding_tax_percent / 100, 2) : 0;

        return [
            'billing_profile_id' => $profile->id, 'slot_pack_id' => $pack->id, 'slot_pack_name' => $pack->name,
            'slot_pack_weekly_price' => (float) $pack->weekly_price, 'apply_withholding_tax' => (bool) $profile->apply_withholding_tax,
            'operation' => 'slot', 'slot_fee' => round($fee, 2), 'net_total' => round($net, 2), 'tips_total' => round($tips, 2),
            'net_without_tips' => round($net - $tips, 2), 'driver_share' => round($net - $tips, 2), 'company_share' => 0,
            'percent_company' => 0, 'percent_driver' => 100, 'rent_total' => 0, 'expenses_total' => round($expenses, 2),
            'vat_amount' => $vat, 'vat_percent' => (float) $profile->vat_percent, 'vat_refund_mode' => $profile->vat_refund_mode?->value,
            'withholding_amount' => $withholding, 'withholding_tax_percent' => (float) $profile->withholding_tax_percent,
            'amount_payable' => round($net + $vat - $withholding - $expenses - $fee, 2), 'vat_multiplier' => 1,
        ];
    }
}
