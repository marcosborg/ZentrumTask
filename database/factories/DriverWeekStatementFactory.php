<?php

namespace Database\Factories;

use App\Enums\StatementStatus;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DriverWeekStatement>
 */
class DriverWeekStatementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfWeek()->subWeeks($this->faker->numberBetween(0, 10));

        return [
            'driver_id' => Driver::factory(),
            'billing_profile_id' => DriverBillingProfile::factory(),
            'tvde_week_id' => null,
            'week_start_date' => $start->toDateString(),
            'week_end_date' => $start->copy()->endOfWeek()->toDateString(),
            'gross_total' => 1000,
            'net_total' => 800,
            'tips_total' => 50,
            'company_share' => 320,
            'driver_share' => 480,
            'vat_amount' => 0,
            'withholding_amount' => 0,
            'expenses_total' => 100,
            'rent_amount' => 0,
            'additional_fees_total' => 0,
            'amount_payable_to_driver' => 430,
            'status' => StatementStatus::Draft,
            'calculated_at' => now(),
        ];
    }
}
