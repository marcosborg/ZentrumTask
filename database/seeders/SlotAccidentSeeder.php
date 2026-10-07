<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SlotAccidentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SlotAccident::factory()->create();
    }
}
