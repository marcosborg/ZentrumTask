<?php

namespace App\Filament\Resources\Services\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Descricao')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('icon')
                    ->label('Icone (Font Awesome)')
                    ->searchable()
                    ->options(self::iconOptions())
                    ->placeholder('Escolha um ícone'),
                Select::make('icon_color')
                    ->label('Cor do icone')
                    ->searchable()
                    ->options(self::colorOptions())
                    ->placeholder('Escolha a cor'),
            ]);
    }

    protected static function iconOptions(): array
    {
        return [
            'fa-solid fa-user-plus' => 'User Plus',
            'fa-solid fa-car' => 'Car',
            'fa-solid fa-car-side' => 'Car Side',
            'fa-solid fa-car-rear' => 'Car Rear',
            'fa-solid fa-van-shuttle' => 'Van Shuttle',
            'fa-solid fa-truck' => 'Truck',
            'fa-solid fa-helmet-safety' => 'Helmet Safety',
            'fa-solid fa-circle-info' => 'Info',
            'fa-solid fa-briefcase' => 'Briefcase',
            'fa-solid fa-phone' => 'Phone',
            'fa-solid fa-envelope' => 'Envelope',
            'fa-solid fa-map-location-dot' => 'Map Location',
            'fa-solid fa-star' => 'Star',
            'fa-solid fa-thumbs-up' => 'Thumbs Up',
            'fa-solid fa-flag' => 'Flag',
            'fa-solid fa-key' => 'Key',
            'fa-solid fa-shield-halved' => 'Shield',
            'fa-solid fa-gauge-high' => 'Gauge High',
        ];
    }

    protected static function colorOptions(): array
    {
        return [
            '#2dd4bf' => 'Teal',
            '#625de3' => 'Roxo',
            '#e97e3c' => 'Laranja',
            '#d9534f' => 'Vermelho',
            '#3b82f6' => 'Azul',
            '#22c55e' => 'Verde',
            '#f59e0b' => 'Âmbar',
            '#475569' => 'Cinza',
            '#ffffff' => 'Branco',
        ];
    }
}
