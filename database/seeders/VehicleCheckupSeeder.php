<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VehicleCheckupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\VehicleCheckup::factory()->create();
    }
}
