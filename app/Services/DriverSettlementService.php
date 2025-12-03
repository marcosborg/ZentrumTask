<?php

namespace App\Services;

use App\Enums\StatementStatus;
use App\Enums\VatRefundMode;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\DriverWeekStatement;
use App\Models\DriverWeekStatementItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DriverSettlementService
{
    /**
     * Gera um extrato semanal a partir de inputs agregados, aplicando as regras do perfil de faturacao.
     *
     * @param  array{
     *     tvde_week_id?: int|null,
     *     week_start_date?: string|null,
     *     week_end_date?: string|null,
     *     gross_total?: float|int|string|null,
     *     net_total?: float|int|string|null,
     *     tips_total?: float|int|string|null,
     *     expenses_total?: float|int|string|null,
     *     rent_amount?: float|int|string|null,
     *     additional_fees_total?: float|int|string|null,
     * }  $inputs
     */
    public function createStatementFromInputs(
        Driver $driver,
        DriverBillingProfile $profile,
        array $inputs
    ): DriverWeekStatement {
        return DB::transaction(function () use ($driver, $profile, $inputs): DriverWeekStatement {
            $grossTotal = $this->toFloat($inputs['gross_total'] ?? 0);
            $netTotal = $this->toFloat($inputs['net_total'] ?? 0);
            $tipsTotal = $this->toFloat($inputs['tips_total'] ?? 0);
            $expensesTotal = $this->toFloat($inputs['expenses_total'] ?? 0);
            $rentAmount = $this->toFloat($inputs['rent_amount'] ?? $profile->vehicle_rent_value ?? 0);

            $companyShare = $netTotal * ($this->toFloat($profile->percent_company) / 100);
            $driverShare = $netTotal * ($this->toFloat($profile->percent_driver) / 100);

            $vatAmount = 0;
            if ($profile->vat_refund_mode === VatRefundMode::DriverDeliversVat) {
                $vatAmount = $netTotal * ($this->toFloat($profile->vat_percent) / 100);
            }

            $tipsToDriver = $profile->tips_to_driver ? $tipsTotal : 0;

            $withholding = 0;
            if ($profile->apply_withholding_tax && $profile->withholding_tax_percent) {
                $withholdingBase = $driverShare + $tipsToDriver;
                $withholding = $withholdingBase * ($this->toFloat($profile->withholding_tax_percent) / 100);
            }

            $feesFixed = $this->toFloat($profile->additional_fixed_fee ?? 0);
            $feesPercent = $this->toFloat($profile->additional_percent_fee ?? 0);
            $additionalFees = $inputs['additional_fees_total'] ?? ($feesFixed + ($feesPercent ? $netTotal * ($feesPercent / 100) : 0));
            $additionalFees = $this->toFloat($additionalFees);

            $amountPayable = $driverShare + $tipsToDriver + $vatAmount - $expensesTotal - $rentAmount - $additionalFees - $withholding;

            $statement = DriverWeekStatement::query()->create([
                'driver_id' => $driver->id,
                'billing_profile_id' => $profile->id,
                'tvde_week_id' => $inputs['tvde_week_id'] ?? null,
                'week_start_date' => $inputs['week_start_date'] ?? null,
                'week_end_date' => $inputs['week_end_date'] ?? null,
                'gross_total' => $grossTotal,
                'net_total' => $netTotal,
                'tips_total' => $tipsTotal,
                'company_share' => $companyShare,
                'driver_share' => $driverShare,
                'vat_amount' => $vatAmount,
                'withholding_amount' => $withholding,
                'expenses_total' => $expensesTotal,
                'rent_amount' => $rentAmount,
                'additional_fees_total' => $additionalFees,
                'amount_payable_to_driver' => $amountPayable,
                'status' => StatementStatus::Draft,
                'calculated_at' => now(),
            ]);

            $items = collect([
                [
                    'type' => 'income',
                    'description' => 'Quota motorista sobre líquido',
                    'amount' => $driverShare,
                ],
                [
                    'type' => 'tip',
                    'description' => 'Gorjetas',
                    'amount' => $tipsToDriver,
                ],
                [
                    'type' => 'vat',
                    'description' => 'IVA a entregar pelo motorista',
                    'amount' => $vatAmount,
                ],
                [
                    'type' => 'expense',
                    'description' => 'Despesas imputáveis ao motorista',
                    'amount' => -1 * $expensesTotal,
                ],
                [
                    'type' => 'rent',
                    'description' => 'Aluguer de viatura',
                    'amount' => -1 * $rentAmount,
                ],
                [
                    'type' => 'fee',
                    'description' => 'Taxas administrativas',
                    'amount' => -1 * $additionalFees,
                ],
                [
                    'type' => 'withholding',
                    'description' => 'Retenção na fonte',
                    'amount' => -1 * $withholding,
                ],
            ])->filter(fn (array $item): bool => abs($this->toFloat($item['amount'])) > 0);

            $this->storeItems($statement, $items);

            return $statement->load(['items', 'driver', 'billingProfile']);
        });
    }

    protected function storeItems(DriverWeekStatement $statement, Collection $items): void
    {
        $items->each(function (array $item) use ($statement): void {
            DriverWeekStatementItem::query()->create([
                'driver_week_statement_id' => $statement->id,
                'type' => $item['type'],
                'description' => $item['description'],
                'amount' => $this->toFloat($item['amount']),
                'meta' => $item['meta'] ?? null,
            ]);
        });
    }

    protected function toFloat(int|float|string|null $value): float
    {
        return round((float) $value, 2);
    }
}
