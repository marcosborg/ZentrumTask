<?php

namespace Database\Seeders;

use App\Models\RentalVan;
use Illuminate\Database\Seeder;

class RentalVanDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        RentalVan::query()->firstOrCreate(['license_plate' => 'DEMO-VAN-01'], [
            'name' => 'DEMONSTRAÇÃO — Carrinha de mercadorias', 'status' => 'draft',
            'description' => 'Exemplo de configuração. Substitua pelos dados reais antes de publicar.',
            'volume_m3' => 12, 'payload_kg' => 1200, 'cargo_dimensions' => '320 × 175 × 190 cm',
            'seats' => 3, 'fuel' => 'Gasóleo', 'transmission' => 'Manual',
            'self_drive' => true, 'with_driver' => true, 'minimum_hours' => 1,
            'buffer_minutes' => 30, 'lead_hours' => 2,
        ]);
    }
}
