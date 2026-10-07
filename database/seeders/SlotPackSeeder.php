<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SlotPackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        foreach (['base' => ['Base', 30], 'premium' => ['Premium', 50]] as $code => [$name, $price]) {

            \App\Models\SlotPack::query()->firstOrCreate(['code' => $code, 'valid_from' => '2026-10-07'], ['name' => $name, 'weekly_price' => $price, 'benefits' => 'Check-up anual, preços descontados e atendimento prioritário na oficina.']);

        }

    }
}
