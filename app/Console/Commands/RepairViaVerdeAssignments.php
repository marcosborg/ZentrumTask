<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\ViaVerdeTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

class RepairViaVerdeAssignments extends Command
{
    protected $signature = 'via-verde:repair-assignments
        {--period-start= : Optional period start (Y-m-d)}
        {--period-end= : Optional period end (Y-m-d)}
        {--only-unassigned : Process only rows not assigned as ok}
        {--dry-run : Preview changes without persisting}';

    protected $description = 'Repair Via Verde vehicle / driver assignments based on row plate and active allocation';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        try {
            [$periodStart, $periodEnd] = $this->resolvePeriod();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $vehicleMap = $this->vehicleMap();
        $isDryRun = (bool) $this->option('dry-run');
        $onlyUnassigned = (bool) $this->option('only-unassigned');

        $scanned = 0;
        $updated = 0;
        $noVehicleFound = 0;
        $ambiguousVehicle = 0;
        $okCount = 0;
        $ambiguousDriverCount = 0;
        $unassignedDriverCount = 0;

        $query = ViaVerdeTransaction::query()
            ->when(
                $periodStart !== null,
                fn ($builder) => $builder->whereDate('occurred_at', '>=', $periodStart->toDateString())
            )
            ->when(
                $periodEnd !== null,
                fn ($builder) => $builder->whereDate('occurred_at', '<=', $periodEnd->toDateString())
            )
            ->when(
                $onlyUnassigned,
                fn ($builder) => $builder->where('assignment_status', '!=', 'ok')
            )
            ->orderBy('id');

        $query->chunkById(200, function (Collection $transactions) use (
            $vehicleMap,
            $isDryRun,
            &$scanned,
            &$updated,
            &$noVehicleFound,
            &$ambiguousVehicle,
            &$okCount,
            &$ambiguousDriverCount,
            &$unassignedDriverCount
        ): void {
            foreach ($transactions as $transaction) {
                $scanned++;

                $rawPlate = $this->extractPlateFromRawRow(is_array($transaction->raw_row) ? $transaction->raw_row : null);
                $fallbackPlate = $this->normalizePlate($transaction->vehicle_plate);
                $normalizedPlate = $rawPlate ?? $fallbackPlate;

                $targetVehicle = null;

                if ($normalizedPlate !== null && $vehicleMap->has($normalizedPlate)) {
                    $vehicles = $vehicleMap->get($normalizedPlate, collect());

                    if ($vehicles->count() === 1) {
                        $targetVehicle = $vehicles->first();
                    } else {
                        $ambiguousVehicle++;
                    }
                } else {
                    $noVehicleFound++;
                }

                $targetVehicleId = $targetVehicle?->id;
                $targetVehiclePlate = $targetVehicle?->license_plate ?? $transaction->vehicle_plate;
                $targetDriverId = null;
                $targetStatus = 'unassigned_driver';

                if ($targetVehicleId !== null) {
                    $assignment = $this->resolveAssignment(
                        $targetVehicleId,
                        $transaction->occurred_at instanceof Carbon
                            ? $transaction->occurred_at
                            : Carbon::parse((string) $transaction->occurred_at)
                    );

                    $targetDriverId = $assignment['driver_id'];
                    $targetStatus = $assignment['status'];
                }

                if ($targetStatus === 'ok') {
                    $okCount++;
                } elseif ($targetStatus === 'ambiguous_driver') {
                    $ambiguousDriverCount++;
                } else {
                    $unassignedDriverCount++;
                }

                $hasChanges = (int) ($transaction->vehicle_id ?? 0) !== (int) ($targetVehicleId ?? 0)
                    || (string) ($transaction->vehicle_plate ?? '') !== (string) ($targetVehiclePlate ?? '')
                    || (int) ($transaction->driver_id ?? 0) !== (int) ($targetDriverId ?? 0)
                    || (string) ($transaction->assignment_status ?? '') !== $targetStatus;

                if (! $hasChanges) {
                    continue;
                }

                $updated++;

                if ($isDryRun) {
                    continue;
                }

                $transaction->forceFill([
                    'vehicle_id' => $targetVehicleId,
                    'vehicle_plate' => $targetVehiclePlate,
                    'driver_id' => $targetDriverId,
                    'assignment_status' => $targetStatus,
                ])->save();
            }
        });

        $mode = $isDryRun ? 'DRY-RUN' : 'APLICADO';
        $this->info("Resultado ({$mode})");
        $this->line("Rows analisadas: {$scanned}");
        $this->line("Rows atualizadas: {$updated}");
        $this->line("Status ok: {$okCount}");
        $this->line("Status ambiguous_driver: {$ambiguousDriverCount}");
        $this->line("Status unassigned_driver: {$unassignedDriverCount}");
        $this->line("Sem viatura encontrada: {$noVehicleFound}");
        $this->line("Matricula ambigua (multiplas viaturas): {$ambiguousVehicle}");

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function resolvePeriod(): array
    {
        $periodStart = $this->option('period-start');
        $periodEnd = $this->option('period-end');

        if (($periodStart && ! $periodEnd) || ($periodEnd && ! $periodStart)) {
            throw new RuntimeException('Informe ambos --period-start e --period-end, ou nenhum.');
        }

        if (! $periodStart || ! $periodEnd) {
            return [null, null];
        }

        try {
            return [
                Carbon::parse((string) $periodStart)->startOfDay(),
                Carbon::parse((string) $periodEnd)->endOfDay(),
            ];
        } catch (\Throwable) {
            throw new RuntimeException('Datas invalidas. Use Y-m-d para --period-start e --period-end.');
        }
    }

    /**
     * @return array<string, Collection<int, Vehicle>>
     */
    private function vehicleMap(): Collection
    {
        return Vehicle::query()
            ->get(['id', 'license_plate'])
            ->groupBy(fn (Vehicle $vehicle): string => $this->normalizePlate($vehicle->license_plate) ?? '');
    }

    private function normalizePlate(?string $plate): ?string
    {
        if ($plate === null) {
            return null;
        }

        $normalized = strtoupper(str_replace(' ', '', trim($plate)));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  array<string, mixed>|null  $rawRow
     */
    private function extractPlateFromRawRow(?array $rawRow): ?string
    {
        if ($rawRow === null) {
            return null;
        }

        foreach ($rawRow as $key => $value) {
            $normalizedKey = preg_replace('/[^a-z0-9]/', '', strtolower((string) $key)) ?? '';

            if (! in_array($normalizedKey, ['licenseplate', 'matricula'], true)) {
                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            return $this->normalizePlate($value);
        }

        return null;
    }

    /**
     * @return array{driver_id:int|null, status:string}
     */
    private function resolveAssignment(int $vehicleId, Carbon $occurredAt): array
    {
        $allocations = VehicleAllocation::query()
            ->where('vehicle_id', $vehicleId)
            ->where('starts_at', '<=', $occurredAt)
            ->where(function ($query) use ($occurredAt): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $occurredAt);
            })
            ->get(['driver_id']);

        if ($allocations->count() === 1) {
            return [
                'driver_id' => (int) $allocations->first()->driver_id,
                'status' => 'ok',
            ];
        }

        if ($allocations->count() > 1) {
            return [
                'driver_id' => null,
                'status' => 'ambiguous_driver',
            ];
        }

        return [
            'driver_id' => null,
            'status' => 'unassigned_driver',
        ];
    }
}
