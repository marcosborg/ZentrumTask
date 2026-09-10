<?php

namespace App\Filament\Resources\RentalVans\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RentalVanForm
{
    private static function money(string $name, string $label): TextInput
    {
        return TextInput::make($name)->label($label)->numeric()->minValue(0)->maxValue(10000)->step('0.01')->prefix('€')->helperText('Valor final com IVA incluído.')
            ->formatStateUsing(fn ($state) => $state === null ? null : $state / 100)
            ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) round((float) $state * 100) : null);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Carrinha e publicação')->columns(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Nome comercial')->required()->maxLength(255),
                TextInput::make('license_plate')->label('Matrícula (apenas interna)')->required()->unique(ignoreRecord: true)->maxLength(30),
                Select::make('status')->label('Estado')->options(['draft' => 'Rascunho', 'published' => 'Publicada', 'archived' => 'Arquivada'])->default('draft')->required(),
                Toggle::make('featured')->label('Destaque na página inicial'),
                Textarea::make('description')->label('Descrição')->maxLength(10000)->columnSpanFull(),
                FileUpload::make('photos')->label('Fotografias — a primeira é a capa')->image()->multiple()->reorderable()->maxFiles(20)->maxSize(8192)->disk('rental_vans')->visibility('public')->directory('rental-vans')->columnSpanFull(),
            ]),
            Section::make('Características e equipamentos')->columns(3)->columnSpanFull()->schema([
                TextInput::make('volume_m3')->label('Volume útil (m³)')->numeric()->minValue(0.1)->maxValue(100),
                TextInput::make('payload_kg')->label('Carga máxima (kg)')->integer()->minValue(1)->maxValue(50000),
                TextInput::make('cargo_dimensions')->label('Comprimento × largura × altura (cm)')->maxLength(255),
                TextInput::make('seats')->label('Lugares')->integer()->minValue(1)->maxValue(9),
                Select::make('fuel')->label('Combustível')->options(['Gasóleo' => 'Gasóleo', 'Gasolina' => 'Gasolina', 'Elétrico' => 'Elétrico', 'Híbrido' => 'Híbrido']),
                Select::make('transmission')->label('Transmissão')->options(['Manual' => 'Manual', 'Automática' => 'Automática']),
                TagsInput::make('equipment')->label('Equipamentos')->columnSpanFull(),
            ]),
            Section::make('Modalidades e preços')->columns(2)->columnSpanFull()->schema([
                Toggle::make('self_drive')->label('Sem motorista')->default(true),
                Toggle::make('with_driver')->label('Com motorista'),
                self::money('self_drive_rate', 'Sem motorista / hora'),
                self::money('with_driver_rate', 'Com motorista / hora'),
                self::money('deposit', 'Caução (separada do aluguer)')->default(0)->required(),
                TextInput::make('minimum_hours')->label('Mínimo de horas')->integer()->minValue(1)->maxValue(720)->default(1)->required(),
                TextInput::make('buffer_minutes')->label('Preparação entre reservas (minutos)')->integer()->minValue(0)->maxValue(1440)->default(30)->required(),
                TextInput::make('lead_hours')->label('Antecedência mínima (horas)')->integer()->minValue(0)->maxValue(720)->default(2)->required(),
            ]),
            Section::make('Levantamento e condições')->columns(2)->columnSpanFull()->schema([
                TextInput::make('opens_at')->label('Abertura')->type('time')->default('08:00')->required(),
                TextInput::make('closes_at')->label('Fecho')->type('time')->default('20:00')->required()->after('opens_at'),
                TextInput::make('pickup_location')->label('Local de levantamento / devolução')->maxLength(255)->columnSpanFull(),
                Textarea::make('mileage_terms')->label('Quilometragem incluída e excedente')->maxLength(5000),
                Textarea::make('fuel_terms')->label('Combustível / carregamento')->maxLength(5000),
                Textarea::make('cancellation_terms')->label('Cancelamento')->maxLength(5000),
                Textarea::make('rental_terms')->label('Condições de aluguer e requisitos do condutor')->maxLength(10000),
            ]),
        ]);
    }
}
