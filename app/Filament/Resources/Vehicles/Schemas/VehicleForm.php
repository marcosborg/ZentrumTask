<?php

namespace App\Filament\Resources\Vehicles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class VehicleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificacao')
                    ->columns(3)
                    ->components([
                        TextInput::make('license_plate')
                            ->label('Matricula')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(20),
                        TextInput::make('vin')
                            ->label('VIN')
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        TextInput::make('make')
                            ->label('Marca')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('model')
                            ->label('Modelo')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('trim')
                            ->label('Versao')
                            ->maxLength(255),
                        TextInput::make('year')
                            ->label('Ano')
                            ->numeric()
                            ->minValue(1900)
                            ->maxValue((int) date('Y') + 1),
                    ]),
                Section::make('Caracteristicas')
                    ->columns(3)
                    ->components([
                        Select::make('fuel_type')
                            ->label('Combustivel')
                            ->options([
                                'diesel' => 'Diesel',
                                'gasoline' => 'Gasolina',
                                'hybrid' => 'Hibrido',
                                'electric' => 'Eletrico',
                            ])
                            ->native(false),
                        Select::make('transmission')
                            ->label('Transmissao')
                            ->options([
                                'manual' => 'Manual',
                                'auto' => 'Automatica',
                            ])
                            ->native(false),
                        TextInput::make('color')
                            ->label('Cor')
                            ->maxLength(50),
                        TextInput::make('seats')
                            ->label('Lugares')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(12),
                        TextInput::make('engine_cc')
                            ->label('Cilindrada (cc)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('power_kw')
                            ->label('Potencia (kW)')
                            ->numeric()
                            ->minValue(0),
                    ]),
                Section::make('Operacao')
                    ->columns(3)
                    ->components([
                        TextInput::make('current_odometer')
                            ->label('Quilometragem atual')
                            ->numeric()
                            ->minValue(0),
                        Select::make('status')
                            ->label('Estado')
                            ->options([
                                'available' => 'Disponivel',
                                'allocated' => 'Alocada',
                                'maintenance' => 'Manutencao',
                                'accident' => 'Acidente',
                                'sold' => 'Vendida',
                                'inactive' => 'Inativa',
                            ])
                            ->default('available')
                            ->native(false),
                        Select::make('source')
                            ->label('Source')
                            ->options([
                                'tvde' => 'TVDE',
                                'outsource' => 'Outsource',
                                'company' => 'Company',
                                'private' => 'Private',
                            ])
                            ->default('tvde')
                            ->required()
                            ->native(false),
                        Textarea::make('notes')
                            ->label('Notas')
                            ->columnSpanFull(),
                    ]),
                Section::make('Financeiro')
                    ->columns(2)
                    ->components([
                        DatePicker::make('acquisition_date')
                            ->label('Data de aquisicao')
                            ->native(false),
                        TextInput::make('acquisition_cost')
                            ->label('Custo de aquisicao')
                            ->numeric()
                            ->step('0.01')
                            ->prefix('EUR'),
                    ]),
                Section::make('Fotos')
                    ->columnSpanFull()
                    ->columns(1)
                    ->components([
                        SpatieMediaLibraryFileUpload::make('vehicle_photos')
                            ->label('Fotos')
                            ->collection('vehicle_photos')
                            ->multiple()
                            ->columnSpanFull()
                            ->panelLayout('grid')
                            ->itemPanelAspectRatio(1)
                            ->imagePreviewHeight('96')
                            ->imageResizeMode('cover')
                            ->extraAttributes([
                                'class' => 'vehicle-photo-upload',
                            ])
                            ->downloadable()
                            ->openable()
                            ->reorderable()
                            ->preserveFilenames()
                            ->getUploadedFileUsing(static function (SpatieMediaLibraryFileUpload $component, string $file): ?array {
                                $media = Media::query()->where('uuid', $file)->first();

                                if (! $media) {
                                    return null;
                                }

                                $conversion = $component->getConversion();

                                return [
                                    'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
                                    'size' => $media->getAttributeValue('size'),
                                    'type' => $media->getAttributeValue('mime_type'),
                                    'url' => route('media.proxy', [
                                        'uuid' => $media->getAttributeValue('uuid'),
                                        'conversion' => ($conversion && $media->hasGeneratedConversion($conversion)) ? $conversion : null,
                                    ]),
                                ];
                            }),
                    ]),
            ]);
    }
}
