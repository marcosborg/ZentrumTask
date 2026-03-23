<?php

namespace App\Console\Commands;

use App\Models\VehicleAllocation;
use App\Models\ViaVerdeTransaction;
use App\Services\ViaVerdeCsvImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RepairViaVerdeDates extends Command
{
    protected $signature = 'via-verde:repair-dates
        {--source-file= : Restrict to one imported source file}
        {--dry-run : Preview changes without persisting}';

    protected $description = 'Repair Via Verde occurred_at timestamps from raw rows and refresh driver assignments';

    public function handle(ViaVerdeCsvImportService $service): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $sourceFile = $this->option('source-file');

        $query = ViaVerdeTransaction::query()
            ->when(
                is_string($sourceFile) && trim($sourceFile) !== '',
                fn ($builder) => $builder->where('source_file', trim((string) $sourceFile))
            )
            ->whereNotNull('raw_row')
            ->orderBy('id');

        $scanned = 0;
        $updated = 0;
        $skippedMissingRawDate = 0;
        $skippedCollision = 0;
        $unchanged = 0;

        $query->chunkById(200, function (Collection $transactions) use (
            $service,
            $isDryRun,
            &$scanned,
            &$updated,
            &$skippedMissingRawDate,
            &$skippedCollision,
            &$unchanged
        ): void {
            $vehicleIds = $transactions
                ->pluck('vehicle_id')
                ->filter()
                ->map(fn (mixed $vehicleId): int => (int) $vehicleId)
                ->unique()
                ->values()
                ->all();

            $allocationsByVehicle = VehicleAllocation::query()
                ->whereIn('vehicle_id', $vehicleIds)
                ->orderBy('starts_at')
                ->get(['vehicle_id', 'driver_id', 'starts_at', 'ends_at'])
                ->groupBy('vehicle_id');

            foreach ($transactions as $transaction) {
                $scanned++;

                $rawRow = is_array($transaction->raw_row) ? $transaction->raw_row : null;
                if ($rawRow === null) {
                    $skippedMissingRawDate++;

                    continue;
                }

                $correctedOccurredAt = $service->extractOccurredAtFromRawRow($rawRow);

                if (! $correctedOccurredAt instanceof Carbon) {
                    $skippedMissingRawDate++;

                    continue;
                }

                $correctedExternalRef = $service->buildExternalRef(
                    $correctedOccurredAt,
                    $transaction->location,
                    (float) $transaction->amount,
                    (string) ($transaction->type ?? 'via_verde')
                );

                $hasCollision = ViaVerdeTransaction::query()
                    ->where('vehicle_id', $transaction->vehicle_id)
                    ->where('external_ref', $correctedExternalRef)
                    ->whereKeyNot($transaction->id)
                    ->exists();

                if ($hasCollision) {
                    $skippedCollision++;

                    continue;
                }

                $assignment = $this->resolveAssignment(
                    $allocationsByVehicle->get((int) $transaction->vehicle_id, collect()),
                    $correctedOccurredAt
                );

                $currentOccurredAt = $transaction->occurred_at instanceof Carbon
                    ? $transaction->occurred_at
                    : Carbon::parse((string) $transaction->occurred_at);

                $hasChanges = $currentOccurredAt->format('Y-m-d H:i:s') !== $correctedOccurredAt->format('Y-m-d H:i:s')
                    || (string) $transaction->external_ref !== $correctedExternalRef
                    || (int) ($transaction->driver_id ?? 0) !== (int) ($assignment['driver_id'] ?? 0)
                    || (string) ($transaction->assignment_status ?? '') !== $assignment['status'];

                if (! $hasChanges) {
                    $unchanged++;

                    continue;
                }

                $updated++;

                if ($isDryRun) {
                    continue;
                }

                $transaction->forceFill([
                    'occurred_at' => $correctedOccurredAt,
                    'external_ref' => $correctedExternalRef,
                    'driver_id' => $assignment['driver_id'],
                    'assignment_status' => $assignment['status'],
                ])->save();
            }
        });

        $mode = $isDryRun ? 'DRY-RUN' : 'APLICADO';
        $this->info("Resultado ({$mode})");
        $this->line("Rows analisadas: {$scanned}");
        $this->line("Rows atualizadas: {$updated}");
        $this->line("Rows sem alteracoes: {$unchanged}");
        $this->line("Rows sem data valida no raw_row: {$skippedMissingRawDate}");
        $this->line("Rows ignoradas por colisao de external_ref: {$skippedCollision}");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, VehicleAllocation>  $allocations
     * @return array{driver_id:int|null, status:string}
     */
    private function resolveAssignment(Collection $allocations, Carbon $occurredAt): array
    {
        $matches = $allocations->filter(function (VehicleAllocation $allocation) use ($occurredAt): bool {
            $start = $allocation->starts_at instanceof Carbon ? $allocation->starts_at : Carbon::parse((string) $allocation->starts_at);
            $end = $allocation->ends_at
                ? ($allocation->ends_at instanceof Carbon ? $allocation->ends_at : Carbon::parse((string) $allocation->ends_at))
                : null;

            if ($start->gt($occurredAt)) {
                return false;
            }

            if ($end && $end->lt($occurredAt)) {
                return false;
            }

            return true;
        })->values();

        if ($matches->count() === 1) {
            return [
                'driver_id' => (int) $matches->first()->driver_id,
                'status' => 'ok',
            ];
        }

        if ($matches->count() > 1) {
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
