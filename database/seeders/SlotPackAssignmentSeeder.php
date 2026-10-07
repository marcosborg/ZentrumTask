<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SlotPackAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SlotPackAssignment::factory()->create();
    }
}
