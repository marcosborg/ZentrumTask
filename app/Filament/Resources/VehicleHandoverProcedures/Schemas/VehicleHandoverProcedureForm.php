<?php

namespace App\Filament\Resources\VehicleHandoverProcedures\Schemas;

use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleHandoverProcedure;
use App\Services\VehicleHandoverProcedureService;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Log;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

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
                            ->map(fn (array $zone) => self::handoverUpload("guided_photo_items.{$zone['key']}.photo")
                                ->label($zone['label'])
                                ->disk('public')
                                ->directory('vehicle-handovers/guided-photos')
                                ->visibility('public'))
                            ->all()
                    ),
                Section::make('Videos')
                    ->description('Grava ou anexa, quando disponivel, um video do exterior e outro do interior.')
                    ->columns(2)
                    ->components([
                        self::handoverUpload('video_items.exterior')
                            ->label('Video exterior')
                            ->disk('public')
                            ->directory('vehicle-handovers/videos')
                            ->visibility('public'),
                        self::handoverUpload('video_items.interior')
                            ->label('Video interior')
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
                                        self::handoverUpload('photo')
                                            ->label('Foto do dano')
                                            ->disk('public')
                                            ->directory('vehicle-handovers/damage-photos')
                                            ->visibility('public')
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Avarias')
                    ->description('Regista problemas mecanicos, eletricos ou outros que nao sejam visiveis em fotografia.')
                    ->components([
                        Repeater::make('fault_items')
                            ->label('Avarias registadas')
                            ->defaultItems(0)
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Select::make('type')
                                            ->label('Tipo')
                                            ->options($service->faultTypes())
                                            ->required()
                                            ->native(false),
                                        Select::make('severity')
                                            ->label('Prioridade')
                                            ->options([
                                                'low' => 'Baixa',
                                                'medium' => 'Media',
                                                'high' => 'Alta',
                                                'immobilized' => 'Viatura imobilizada',
                                            ])
                                            ->native(false),
                                        Textarea::make('description')
                                            ->label('Sintoma / descricao')
                                            ->required()
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ]),
                Section::make('Fotos gerais')
                    ->components([
                        self::handoverUpload('general_photos')
                            ->label('Fotos opcionais')
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
                            ->disabled(fn (?VehicleHandoverProcedure $record): bool => $record?->status === 'completed')
                            ->view('filament.forms.components.signature-pad'),
                        ViewField::make('driver_signature_data_url')
                            ->label('Assinatura do motorista')
                            ->required()
                            ->disabled()
                            ->view('filament.forms.components.signature-pad'),
                    ]),
            ]);
    }

    protected static function handoverUpload(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->saveUploadedFileUsing(static function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                try {
                    if (! $file->exists()) {
                        return null;
                    }

                    if (
                        $component->shouldMoveFiles()
                        && ($component->getDiskName() === (fn (): string => $this->disk)->call($file))
                    ) {
                        $path = trim($component->getDirectory().'/'.$component->getUploadedFileNameForStorage($file), '/');

                        $component->getDisk()->move((fn (): string => $this->path)->call($file), $path);

                        return $path;
                    }

                    $storeMethod = $component->getVisibility() === 'public' ? 'storePubliclyAs' : 'storeAs';

                    return $file->{$storeMethod}(
                        $component->getDirectory(),
                        $component->getUploadedFileNameForStorage($file),
                        $component->getDiskName(),
                    );
                } catch (Throwable $exception) {
                    Log::warning('vehicle_handover_upload_failed', [
                        'field' => $component->getStatePath(),
                        'file' => $file->getClientOriginalName(),
                        'error' => $exception->getMessage(),
                    ]);

                    return null;
                }
            });
    }
}
