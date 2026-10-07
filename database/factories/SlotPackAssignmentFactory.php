<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SlotPackAssignment>
 */
class SlotPackAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'driver_participation_id' => \App\Models\DriverParticipation::factory(),
            'slot_pack_id' => \App\Models\SlotPack::factory(), 'starts_at' => now()->startOfWeek(),
        ];
    }
}
