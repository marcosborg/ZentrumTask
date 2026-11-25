<?php

namespace Database\Seeders;

use App\Models\AdvertisingFormat;
use Illuminate\Database\Seeder;

class AdvertisingFormatSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['City', 'Duas portas traseiras. Uma presença discreta para o comércio de proximidade.', 'city'],
            ['Urban', 'Quatro portas laterais para dar mais espaço à identidade da marca.', 'urban'],
            ['Impact', 'Portas laterais e guarda-lamas traseiros para uma campanha de maior presença visual.', 'impact'],
            ['Interior', 'Suportes físicos ou digitais dentro da viatura. Disponibilidade sob consulta.', null],
        ] as [$name, $description, $image]) {
            AdvertisingFormat::firstOrCreate(['name' => $name], compact('description', 'image'));
        }
    }
}
