<?php

namespace App\Filament\Pages;

use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

class VehicleAllocationTimeline extends Page
{
    protected static ?string $title = 'Alocacoes de viaturas';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static UnitEnum|string|null $navigationGroup = 'TVDE';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Alocacoes & Utilizacao';

    protected string $view = 'filament.pages.vehicle-allocation-timeline';

    public int $rangeDays = 90;

    public string $rangeStartLabel = '';

    public string $rangeEndLabel = '';

    /** @var array<int, array<string, mixed>> */
    public array $timeline = [];

    /** @var array<int, array<string, mixed>> */
    public array $utilization = [];

    public function mount(): void
    {
        $this->loadData();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('createAllocation')
                ->label('Adicionar alocacao')
                ->icon(Heroicon::OutlinedPlusCircle)
                ->modalHeading('Nova alocacao de viatura')
                ->form([
                    Select::make('vehicle_id')
                        ->label('Viatura')
                        ->options(fn (): array => Vehicle::query()
                            ->orderBy('license_plate')
                            ->pluck('license_plate', 'id')
                            ->all())
                        ->searchable()
                        ->required()
                        ->native(false),
                    Select::make('driver_id')
                        ->label('Motorista')
                        ->options(fn (): array => Driver::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required()
                        ->native(false),
                    DateTimePicker::make('starts_at')
                        ->label('Inicio')
                        ->required()
                        ->native(false),
                    DateTimePicker::make('ends_at')
                        ->label('Fim')
                        ->native(false),
                    Select::make('status')
                        ->label('Estado')
                        ->options([
                            'active' => 'Ativa',
                            'closed' => 'Fechada',
                            'planned' => 'Planeada',
                        ])
                        ->default('active')
                        ->required()
                        ->native(false),
                    TextInput::make('start_odometer')
                        ->label('Odometro inicial')
                        ->numeric()
                        ->minValue(0),
                    TextInput::make('end_odometer')
                        ->label('Odometro final')
                        ->numeric()
                        ->minValue(0),
                    TextInput::make('handover_location')
                        ->label('Local de entrega')
                        ->maxLength(255),
                    Textarea::make('notes')
                        ->label('Notas')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    VehicleAllocation::query()->create([
                        'vehicle_id' => $data['vehicle_id'],
                        'driver_id' => $data['driver_id'],
                        'starts_at' => $data['starts_at'],
                        'ends_at' => $data['ends_at'] ?? null,
                        'status' => $data['status'],
                        'start_odometer' => $data['start_odometer'] ?? null,
                        'end_odometer' => $data['end_odometer'] ?? null,
                        'handover_location' => $data['handover_location'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ]);

                    $this->loadData();

                    Notification::make()
                        ->title('Alocacao criada')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function loadData(): void
    {
        $rangeEnd = Carbon::today()->endOfDay();
        $rangeStart = $rangeEnd->copy()->subDays($this->rangeDays - 1)->startOfDay();

        $this->rangeStartLabel = $rangeStart->format('d/m/Y');
        $this->rangeEndLabel = $rangeEnd->format('d/m/Y');

        $totalSeconds = max(1, $rangeEnd->diffInSeconds($rangeStart));

        $vehicles = Vehicle::query()
            ->with([
                'allocations' => function ($query) use ($rangeStart, $rangeEnd) {
                    $query
                        ->with('driver')
                        ->where(function ($query) use ($rangeStart, $rangeEnd) {
                            $query
                                ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
                                ->orWhereBetween('ends_at', [$rangeStart, $rangeEnd])
                                ->orWhere(function ($query) use ($rangeEnd) {
                                    $query->whereNull('ends_at')
                                        ->where('starts_at', '<=', $rangeEnd);
                                });
                        })
                        ->orderBy('starts_at');
                },
            ])
            ->orderBy('license_plate')
            ->get();

        $this->timeline = $vehicles->map(function (Vehicle $vehicle) use ($rangeStart, $rangeEnd, $totalSeconds) {
            $segments = [];
            $allocatedSeconds = 0;
            $currentDriver = null;

            foreach ($vehicle->allocations as $allocation) {
                $start = Carbon::parse($allocation->starts_at);
                $end = $allocation->ends_at ? Carbon::parse($allocation->ends_at) : $rangeEnd;

                $segmentStart = $start->copy()->max($rangeStart);
                $segmentEnd = $end->copy()->min($rangeEnd);

                if ($segmentEnd->lt($rangeStart) || $segmentStart->gt($rangeEnd)) {
                    continue;
                }

                $left = $segmentStart->diffInSeconds($rangeStart) / $totalSeconds * 100;
                $width = max(0.5, $segmentEnd->diffInSeconds($segmentStart) / $totalSeconds * 100);

                $segments[] = [
                    'left' => $left,
                    'width' => $width,
                    'label' => $allocation->driver?->name ?? 'Motorista',
                    'status' => $allocation->status,
                ];

                $allocatedSeconds += $segmentEnd->diffInSeconds($segmentStart);

                if ($allocation->status === 'active' && $allocation->ends_at === null) {
                    $currentDriver = $allocation->driver?->name;
                }
            }

            $utilization = (int) round(($allocatedSeconds / $totalSeconds) * 100);

            return [
                'license_plate' => $vehicle->license_plate,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'current_driver' => $currentDriver,
                'segments' => $segments,
                'utilization' => $utilization,
            ];
        })->values()->all();

        $this->utilization = $vehicles->map(function (Vehicle $vehicle) use ($rangeStart, $rangeEnd, $totalSeconds) {
            $allocatedSeconds = 0;
            $currentAllocation = null;

            foreach ($vehicle->allocations as $allocation) {
                $start = Carbon::parse($allocation->starts_at);
                $end = $allocation->ends_at ? Carbon::parse($allocation->ends_at) : $rangeEnd;

                $segmentStart = $start->copy()->max($rangeStart);
                $segmentEnd = $end->copy()->min($rangeEnd);

                if ($segmentEnd->lt($rangeStart) || $segmentStart->gt($rangeEnd)) {
                    continue;
                }

                $allocatedSeconds += $segmentEnd->diffInSeconds($segmentStart);

                if ($allocation->status === 'active' && $allocation->ends_at === null) {
                    $currentAllocation = $allocation;
                }
            }

            $utilization = (int) round(($allocatedSeconds / $totalSeconds) * 100);

            return [
                'license_plate' => $vehicle->license_plate,
                'label' => trim($vehicle->make.' '.$vehicle->model),
                'current_driver' => $currentAllocation?->driver?->name,
                'current_start' => $currentAllocation?->starts_at,
                'utilization' => $utilization,
                'downtime' => max(0, 100 - $utilization),
            ];
        })->values()->all();
    }
}
