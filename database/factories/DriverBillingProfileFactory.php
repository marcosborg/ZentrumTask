<?php

namespace Database\Factories;

use App\Enums\TaxpayerType;
use App\Enums\VatRefundMode;
use App\Enums\VehicleRentType;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DriverBillingProfile>
 */
class DriverBillingProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'active' => true,
            'valid_from' => now()->subMonth()->startOfWeek(),
            'valid_to' => null,
            'taxpayer_type' => TaxpayerType::SelfEmployed,
            'apply_withholding_tax' => false,
            'withholding_tax_percent' => null,
            'vat_percent' => 23.00,
            'vat_refund_mode' => VatRefundMode::None,
            'percent_company' => 40.00,
            'percent_driver' => 60.00,
            'tips_to_driver' => true,
            'vehicle_rent_type' => VehicleRentType::None,
            'vehicle_rent_value' => null,
            'additional_fixed_fee' => 0,
            'additional_percent_fee' => 0,
        ];
    }
}
