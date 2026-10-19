<?php

namespace App\Services;

use App\Models\TeslaVehicleSnapshot;
use App\Models\Vehicle;
use App\Models\VehicleWeeklyMileage;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class VehicleOdometerService
{
    public function record(Vehicle $vehicle, float $odometer, string $periodStart, string $periodEnd, ?TeslaVehicleSnapshot $snapshot = null): VehicleWeeklyMileage
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        if ($end->lt($start) || ! is_finite($odometer) || $odometer < 0) {
            throw ValidationException::withMessages(['odometers.'.$vehicle->getKey() => 'Indique um periodo e uma leitura de km validos.']);
        }

        $previous = VehicleWeeklyMileage::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->whereDate('period_start', $start->copy()->subWeek()->toDateString())
            ->whereDate('period_end', $end->copy()->subWeek()->toDateString())
            ->first();
        $previousOdometer = data_get($previous?->raw_row, 'current_odometer');

        if (! is_numeric($previousOdometer)) {
            $previousSnapshot = TeslaVehicleSnapshot::query()
                ->whereHas('teslaVehicle', fn ($query) => $query->where('vehicle_id', $vehicle->getKey()))
                ->where('is_manual', true)
                ->whereNotNull('odometer')
                ->whereBetween('recorded_at', [$start, $start->copy()->addDay()->endOfDay()])
                ->when($snapshot, fn ($query) => $query->whereKeyNot($snapshot->getKey()))
                ->latest('recorded_at')
                ->first();
            $previousOdometer = $previousSnapshot?->odometer;
        }

        if (is_numeric($previousOdometer) && $odometer < (float) $previousOdometer) {
            throw ValidationException::withMessages(['odometers.'.$vehicle->getKey() => 'A leitura nao pode ser inferior a da semana anterior.']);
        }

        $drivers = $vehicle->allocations()
            ->whereIn('status', ['active', 'closed'])
            ->where('starts_at', '<=', $end)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $start))
            ->pluck('driver_id')->filter()->unique();
        $hasPreviousReading = is_numeric($previousOdometer);

        $reading = VehicleWeeklyMileage::query()
            ->where('vehicle_id', $vehicle->getKey())
            ->whereDate('period_start', $start->toDateString())
            ->whereDate('period_end', $end->toDateString())
            ->first() ?? new VehicleWeeklyMileage([
                'vehicle_id' => $vehicle->getKey(),
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ]);

        $reading->fill([
            'driver_id' => $drivers->count() === 1 ? $drivers->first() : null,
            'assignment_status' => match ($drivers->count()) {
                0 => 'unassigned_driver',
                1 => 'ok',
                default => 'ambiguous_driver',
            },
            'weekly_km' => $hasPreviousReading ? round($odometer - (float) $previousOdometer, 2) : 0,
            'raw_row' => [
                'source' => $snapshot ? 'tesla_manual_snapshot' : 'manual_odometer',
                'current_snapshot_id' => $snapshot?->getKey(),
                'previous_odometer' => $hasPreviousReading ? (float) $previousOdometer : null,
                'current_odometer' => $odometer,
                'has_previous_week_reading' => $hasPreviousReading,
            ],
            'imported_at' => now(),
            'source_file' => $snapshot ? 'tesla_manual_snapshot' : 'manual_odometer',
        ]);

        $reading->save();

        return $reading;
    }
}
