<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SlotAccidentRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\SlotAccidentRequest::factory()->create();
    }
}
