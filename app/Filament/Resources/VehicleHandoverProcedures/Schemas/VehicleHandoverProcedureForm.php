<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Schemas;

use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\VehicleHandoverProcedureService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Forms\Get;
use Filament\Forms\Set;

class VehicleHandoverProcedureForm
{
    public static function configure(Schema $schema): Schema
    {
        $service = app(VehicleHandoverProcedureService::class);

        return $schema
            ->components([
                Section::make('Procedimento')
                    ->columns(3)
                    ->components([
                        Select::make('type')
                            ->label('Tipo')
                            ->options([
                                'delivery' => 'Entrega',
                                'return' => 'Devolucao',
                            ])
                            ->required()
                            ->native(false)
                            ->default('delivery'),
                        DateTimePicker::make('performed_at')
                            ->label('Data e hora')
                            ->required()
                            ->native(false)
                            ->default(now()),
                        Select::make('selection_mode')
                            ->label('Comecar por')
                            ->options([
                                'vehicle' => 'Viatura',
                                'driver' => 'Motorista',
                            ])
                            ->default('vehicle')
                            ->live()
                            ->dehydrated(false)
                            ->native(false),
                        Select::make('vehicle_id')
                            ->label('Viatura')
                            ->options(fn (): array => Vehicle::query()
                                ->orderBy('license_plate')
                                ->get()
                                ->mapWithKeys(fn (Vehicle $vehicle): array => [
                                    $vehicle->id => trim(implode(' ', array_filter([$vehicle->license_plate, $vehicle->make, $vehicle->model]))),
                                ])
                                ->all())
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                if (! $state || $get('selection_mode') !== 'vehicle') {
                                    return;
                                }

                                $vehicle = Vehicle::query()->with('currentAllocation.driver')->find($state);

                                if ($vehicle?->currentAllocation?->driver_id) {
                                    $set('driver_id', $vehicle->currentAllocation->driver_id);
                                }
                            }),
                        Select::make('driver_id')
                            ->label('Motorista')
                            ->options(fn (): array => Driver::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (Driver $driver): array => [
                                    $driver->id => trim(implode(' - ', array_filter([$driver->name, $driver->phone]))),
                                ])
                                ->all())
                            ->searchable()
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, $state): void {
                                if (! $state || $get('selection_mode') !== 'driver') {
                                    return;
                                }

                                $driver = Driver::query()->with('currentAllocation.vehicle')->find($state);

                                if ($driver?->currentAllocation?->vehicle_id) {
                                    $set('vehicle_id', $driver->currentAllocation->vehicle_id);
                                }
                            }),
                        Textarea::make('notes')
                            ->label('Observacoes')
                            ->columnSpanFull(),
                    ]),
                Section::make('Checklist')
                    ->columns(2)
                    ->components(
                        collect($service->checklistItems())
                            ->reject(fn (array $item): bool => $item['key'] === 'photos_inside_outside')
                            ->map(function (array $item): Section {
                                $components = [
                                    Checkbox::make("checklist_payload.{$item['key']}.checked")
                                        ->label($item['label']),
                                ];

                                if ($item['requires_value'] ?? false) {
                                    $checkedPath = "checklist_payload.{$item['key']}.checked";

                                    $components[] = TextInput::make("checklist_payload.{$item['key']}.value")
                                        ->label($item['value_label'] ?? 'Valor')
                                        ->required(fn (Get $get): bool => (bool) $get($checkedPath));
                                } else {
                                    $components[] = Hidden::make("checklist_payload.{$item['key']}.value");
                                }

                                return Section::make($item['label'])
                                    ->compact()
                                    ->columns(1)
                                    ->components($components);
                            })
                            ->all()
                    ),
                Section::make('Mapa fotografico da viatura')
                    ->description('Regista as fotografias guiadas por zona da viatura.')
                    ->columns(2)
                    ->components(
                        collect($service->guidedPhotoZones())
                            ->map(fn (array $zone) => FileUpload::make("guided_photo_items.{$zone['key']}.photo")
                                ->label($zone['label'])
                                ->image()
                                ->disk('public')
                                ->directory('vehicle-handovers/guided-photos')
                                ->visibility('public'))
                            ->all()
                    ),
                Section::make('Videos')
                    ->description('Grava ou anexa, quando disponivel, um video do exterior e outro do interior. Maximo 100MB por video.')
                    ->columns(2)
                    ->components([
                        FileUpload::make('video_items.exterior')
                            ->label('Video exterior')
                            ->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp'])
                            ->maxSize(102400)
                            ->disk('public')
                            ->directory('vehicle-handovers/videos')
                            ->visibility('public'),
                        FileUpload::make('video_items.interior')
                            ->label('Video interior')
                            ->acceptedFileTypes(['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp'])
                            ->maxSize(102400)
                            ->disk('public')
                            ->directory('vehicle-handovers/videos')
                            ->visibility('public'),
                    ]),
                Section::make('Danos')
                    ->components([
                        Repeater::make('damage_items')
                            ->label('Danos registados')
                            ->defaultItems(0)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('type')
                                            ->label('Tipo')
                                            ->options(collect($service->damageTypes())->mapWithKeys(fn (string $value): array => [$value => ucfirst($value)])->all())
                                            ->required()
                                            ->native(false),
                                        Select::make('zone')
                                            ->label('Zona')
                                            ->options(collect($service->vehicleZones())->mapWithKeys(fn (string $value): array => [$value => str_replace('_', ' ', ucfirst($value))])->all())
                                            ->required()
                                            ->native(false),
                                        Textarea::make('description')
                                            ->label('Descricao')
                                            ->columnSpanFull(),
                                        FileUpload::make('photo')
                                            ->label('Foto do dano')
                                            ->image()
                                            ->disk('public')
                                            ->directory('vehicle-handovers/damage-photos')
                                            ->visibility('public')
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Fotos gerais')
                    ->components([
                        FileUpload::make('general_photos')
                            ->label('Fotos opcionais')
                            ->image()
                            ->multiple()
                            ->disk('public')
                            ->directory('vehicle-handovers/general-photos')
                            ->visibility('public')
                            ->reorderable()
                            ->columnSpanFull(),
                    ]),
                Section::make('Assinaturas')
                    ->columns(2)
                    ->components([
                        ViewField::make('operator_signature_data_url')
                            ->label('Assinatura do operador')
                            ->required()
                            ->view('filament.forms.components.signature-pad'),
                        ViewField::make('driver_signature_data_url')
                            ->label('Assinatura do motorista')
                            ->required()
                            ->view('filament.forms.components.signature-pad'),
                    ]),
            ]);
    }
}
