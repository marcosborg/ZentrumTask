<?php

namespace App\Filament\Pages;

use App\Models\Vehicle;
use BackedEnum;
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
