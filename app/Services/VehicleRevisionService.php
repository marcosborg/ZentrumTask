<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleRevision;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class VehicleRevisionService
{
    /** @return list<string> */
    public function relations(): array
    {
        return ['revisions', 'weeklyMileages', 'teslaVehicles.latestOdometerSnapshot'];
    }

    public function nextDate(VehicleRevision $revision): ?Carbon
    {
        return $revision->completed_at && $revision->interval_years
            ? $revision->completed_at->copy()->addYearsNoOverflow($revision->interval_years)
            : null;
    }

    /** @return array{value: ?float, date: ?string, source: string} */
    public function odometer(Vehicle $vehicle): array
    {
        $vehicle->loadMissing($this->relations());
        $readings = collect();

        foreach ($vehicle->weeklyMileages as $mileage) {
            $value = data_get($mileage->raw_row, 'current_odometer');
            if (is_numeric($value) && (float) $value >= 0 && $mileage->period_end->lte(today())) {
                $readings->push(['value' => (float) $value, 'date' => $mileage->period_end->toDateString(), 'source' => 'Leitura semanal', 'timestamp' => $mileage->period_end->timestamp, 'priority' => 0]);
            }
        }

        foreach ($vehicle->teslaVehicles as $tesla) {
            $snapshot = $tesla->latestOdometerSnapshot;
            if ($snapshot !== null) {
                $readings->push(['value' => (float) $snapshot->odometer, 'date' => $snapshot->recorded_at->toDateTimeString(), 'source' => 'Tesla', 'timestamp' => $snapshot->recorded_at->timestamp, 'priority' => 1]);
            }
            if ($tesla->odometer !== null && $tesla->odometer >= 0 && $tesla->last_seen_at !== null && $tesla->last_seen_at->lte(now())) {
                $readings->push(['value' => (float) $tesla->odometer, 'date' => $tesla->last_seen_at->toDateTimeString(), 'source' => 'Tesla', 'timestamp' => $tesla->last_seen_at->timestamp, 'priority' => 1]);
            }
        }

        $latest = $readings->sortBy([['timestamp', 'desc'], ['priority', 'desc']])->first();

        return $latest === null
            ? ['value' => $vehicle->current_odometer === null ? null : (float) $vehicle->current_odometer, 'date' => null, 'source' => 'Ficha da viatura']
            : ['value' => $latest['value'], 'date' => $latest['date'], 'source' => $latest['source']];
    }

    /**
     * @return array{vehicle: Vehicle, revision_id: int, cycle: string, due_at: ?Carbon, next_odometer: ?int, reading: array{value: ?float, date: ?string, source: string}, scheduled_at: ?Carbon, reasons: list<string>, overdue: bool}|null
     */
    public function summary(Vehicle $vehicle): ?array
    {
        $vehicle->loadMissing($this->relations());
        $completed = $vehicle->revisions->whereNotNull('completed_at')->sortBy([['completed_at', 'desc'], ['id', 'desc']])->first();
        $scheduled = $vehicle->revisions->whereNull('completed_at')
            ->filter(fn (VehicleRevision $revision): bool => $revision->scheduled_at !== null && ($completed === null || $revision->scheduled_at->gte($completed->completed_at)))
            ->sortBy([['scheduled_at', 'asc'], ['id', 'desc']])->first();
        $revision = $completed ?? $scheduled;

        if ($revision === null) {
            return null;
        }

        $dueAt = $completed ? $this->nextDate($completed) : $scheduled->scheduled_at;
        $nextOdometer = $completed?->next_odometer;
        $reading = $this->odometer($vehicle);
        $reasons = [];
        $overdue = false;

        if ($dueAt !== null && $dueAt->lte(today()->addDays(30))) {
            $overdue = $dueAt->lte(today());
            $reasons[] = $overdue ? 'Prazo de revisão atingido' : 'Revisão nos próximos 30 dias';
        }

        if ($nextOdometer !== null && $reading['value'] !== null && $nextOdometer - $reading['value'] <= 1000) {
            $kilometresOverdue = $reading['value'] >= $nextOdometer;
            $overdue = $overdue || $kilometresOverdue;
            $reasons[] = $kilometresOverdue ? 'Limite de quilómetros atingido' : 'Faltam até 1.000 km';
        }

        return [
            'vehicle' => $vehicle,
            'revision_id' => $revision->id,
            'cycle' => hash('sha256', implode('|', [$revision->id, $revision->completed_at?->toDateString(), $dueAt?->toDateString(), $nextOdometer])),
            'due_at' => $dueAt,
            'next_odometer' => $nextOdometer,
            'reading' => $reading,
            'scheduled_at' => $scheduled?->scheduled_at,
            'reasons' => $reasons,
            'overdue' => $overdue,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function pending(): Collection
    {
        $pending = collect();
        Vehicle::query()->whereHas('revisions')->with($this->relations())
            ->chunkById(100, function ($vehicles) use ($pending): void {
                foreach ($vehicles as $vehicle) {
                    $summary = $this->summary($vehicle);
                    if ($summary !== null && $summary['reasons'] !== []) {
                        $pending->push($summary);
                    }
                }
            });

        return $pending;
    }
}
