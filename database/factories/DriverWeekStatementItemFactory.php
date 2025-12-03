<?php

namespace Database\Factories;

use App\Models\DriverWeekStatement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DriverWeekStatementItem>
 */
class DriverWeekStatementItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_week_statement_id' => DriverWeekStatement::factory(),
            'type' => 'income',
            'description' => 'Quota motorista sobre líquido',
            'amount' => 100,
            'meta' => null,
        ];
    }
}
