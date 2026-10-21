<?php

namespace Database\Seeders;

use App\Models\VehicleRevision;
use Illuminate\Database\Seeder;

class VehicleRevisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            return;
        }

        VehicleRevision::factory()->create();
        VehicleRevision::factory()->scheduled()->create();
    }
}
