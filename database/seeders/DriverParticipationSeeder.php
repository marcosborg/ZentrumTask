<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DriverParticipationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\DriverParticipation::factory()->create();
    }
}
