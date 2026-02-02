<?php

namespace App\Services;

use App\Models\PrioTransaction;
use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PrioFuelCsvImportService
{
    /**
     * @return array{
     *     total:int,
     *     inserted:int,
     *     updated:int,
     *     skipped:int,
     *     invalid_rows:int,
     *     period_start:string|null,
     *     period_end:string|null,
     *     unassigned_vehicle:int,
     *     unassigned_driver:int,
     *     ambiguous_driver:int
     * }
     */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('CSV nao encontrado: '.$path);
        }

        [$rows, $headers, $headerMap] = $this->readCsv($path);

        if ($rows === []) {
            throw new RuntimeException('CSV sem linhas.');
        }

        $columnMap = $this->resolveColumnMap($headers, $headerMap);
        $vehiclesMap = $this->buildVehicleMaps();

        $parsedRows = [];
        $invalidRows = 0;
        $skipped = 0;
        $occurredAtMin = null;
        $occurredAtMax = null;

        foreach ($rows as $row) {
            $idUsage = $this->normalizeNullable($row[$columnMap['id_usage']] ?? null);
            $cardCode = $this->normalizeNullable($row[$columnMap['card_code']] ?? null);
            $dateValue = $row[$columnMap['start_date']] ?? null;

            if (! $idUsage || ! $cardCode || ! $dateValue) {
                $invalidRows++;
                $skipped++;

                continue;
            }

            $occurredAt = $this->parseDate((string) $dateValue);

            $plateValue = $this->normalizeNullable($row[$columnMap['vehicle_plate']] ?? null);
            $stationId = $this->normalizeNullable($row[$columnMap['station_id']] ?? null);
            $energyValue = $row[$columnMap['energy_kwh']] ?? null;
            $energyKwh = $energyValue !== null ? $this->parseAmount($energyValue, 3) : null;
            $netValueRaw = $row[$columnMap['net_amount']] ?? null;
            $grossValueRaw = $row[$columnMap['gross_amount']] ?? null;

            $netAmount = $netValueRaw !== null ? $this->parseAmount($netValueRaw, 2) : null;
            $grossAmount = $grossValueRaw !== null ? $this->parseAmount($grossValueRaw, 2) : null;
            $vatRate = null;

            if ($netAmount === null && $grossAmount !== null) {
                $netAmount = round($grossAmount / 1.23, 2);
                $vatRate = 23.0;
            }

            if ($netAmount === null) {
                $invalidRows++;
                $skipped++;

                continue;
            }

            $vehicleId = $this->resolveVehicleId($vehiclesMap, $plateValue, $cardCode);

            $assignmentStatus = $vehicleId ? 'unassigned_driver' : 'unassigned_vehicle';

            $parsedRows[] = [
                'occurred_at' => $occurredAt,
                'card_code' => $cardCode,
                'vehicle_plate' => $plateValue,
                'id_usage' => $idUsage,
                'station_id' => $stationId,
                'energy_kwh' => $energyKwh,
                'net_amount' => $netAmount,
                'gross_amount' => $grossAmount,
                'vat_rate' => $vatRate,
                'vehicle_id' => $vehicleId,
                'driver_id' => null,
                'assignment_status' => $assignmentStatus,
                'raw_row' => $this->buildRawRow($row, $columnMap, $headerMap),
            ];

            if (! $occurredAtMin || $occurredAt->lt($occurredAtMin)) {
                $occurredAtMin = $occurredAt;
            }

            if (! $occurredAtMax || $occurredAt->gt($occurredAtMax)) {
                $occurredAtMax = $occurredAt;
            }
        }

        if ($parsedRows === []) {
            return [
                'total' => count($rows),
                'inserted' => 0,
                'updated' => 0,
                'skipped' => $skipped,
                'invalid_rows' => $invalidRows,
                'period_start' => null,
                'period_end' => null,
                'unassigned_vehicle' => 0,
                'unassigned_driver' => 0,
                'ambiguous_driver' => 0,
            ];
        }

        $allocationsByVehicle = $this->loadAllocations($parsedRows, $occurredAtMin, $occurredAtMax);

        foreach ($parsedRows as &$row) {
            if (! $row['vehicle_id']) {
                continue;
            }

            $allocations = $allocationsByVehicle[$row['vehicle_id']] ?? collect();
            $driverId = $this->resolveDriverForAllocation($allocations, $row['occurred_at']);

            if ($driverId === null) {
                $row['assignment_status'] = $this->resolveDriverStatus($allocations, $row['occurred_at']);
            } else {
                $row['driver_id'] = $driverId;
                $row['assignment_status'] = 'ok';
            }
        }
        unset($row);

        $inserted = 0;
        $updated = 0;
        $unassignedVehicle = 0;
        $unassignedDriver = 0;
        $ambiguousDriver = 0;

        DB::transaction(function () use (
            $parsedRows,
            $path,
            &$inserted,
            &$updated,
            &$unassignedVehicle,
            &$unassignedDriver,
            &$ambiguousDriver
        ): void {
            foreach ($parsedRows as $row) {
                $model = PrioTransaction::query()->updateOrCreate(
                    [
                        'card_code' => $row['card_code'],
                        'id_usage' => $row['id_usage'],
                    ],
                    [
                        'occurred_at' => $row['occurred_at'],
                        'vehicle_plate' => $row['vehicle_plate'],
                        'station_id' => $row['station_id'],
                        'energy_kwh' => $row['energy_kwh'],
                        'net_amount' => $row['net_amount'],
                        'gross_amount' => $row['gross_amount'],
                        'vat_rate' => $row['vat_rate'],
                        'vehicle_id' => $row['vehicle_id'],
                        'driver_id' => $row['driver_id'],
                        'assignment_status' => $row['assignment_status'],
                        'raw_row' => $row['raw_row'],
                        'imported_at' => now(),
                        'source_file' => basename($path),
                    ]
                );

                if ($model->wasRecentlyCreated) {
                    $inserted++;
                } else {
                    $updated++;
                }

                if ($row['assignment_status'] === 'unassigned_vehicle') {
                    $unassignedVehicle++;
                } elseif ($row['assignment_status'] === 'unassigned_driver') {
                    $unassignedDriver++;
                } elseif ($row['assignment_status'] === 'ambiguous_driver') {
                    $ambiguousDriver++;
                }
            }
        });

        return [
            'total' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'invalid_rows' => $invalidRows,
            'period_start' => $occurredAtMin?->toDateString(),
            'period_end' => $occurredAtMax?->toDateString(),
            'unassigned_vehicle' => $unassignedVehicle,
            'unassigned_driver' => $unassignedDriver,
            'ambiguous_driver' => $ambiguousDriver,
        ];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<string, string>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel abrir o CSV.');
        }

        $delimiter = $this->detectDelimiter($handle);
        $header = null;

        while (($candidate = fgetcsv($handle, 0, $delimiter)) !== false) {
            $hasContent = count(array_filter($candidate, fn ($value): bool => trim((string) $value) !== '')) > 0;

            if (! $hasContent) {
                continue;
            }

            $header = $candidate;
            break;
        }

        if (! is_array($header)) {
            fclose($handle);
            throw new RuntimeException('CSV sem cabecalho.');
        }

        $normalizedHeader = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);
        $normalizedToOriginal = [];
        foreach ($header as $index => $original) {
            $normalized = $normalizedHeader[$index] ?? 'col_'.$index;
            if (! array_key_exists($normalized, $normalizedToOriginal)) {
                $normalizedToOriginal[$normalized] = $original;
            }
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            $hasContent = count(array_filter($data, fn ($value): bool => trim((string) $value) !== '')) > 0;

            if (! $hasContent) {
                continue;
            }

            $row = [];
            foreach ($data as $index => $value) {
                $key = $normalizedHeader[$index] ?? 'col_'.$index;
                $row[$key] = is_string($value) ? trim($value) : $value;
            }

            if (count($row) === 1 && ($row['col_0'] ?? '') === '') {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return [$rows, $normalizedHeader, $normalizedToOriginal];
    }

    /**
     * @param  resource  $handle
     */
    private function detectDelimiter($handle): string
    {
        $line = null;

        while (($candidate = fgets($handle)) !== false) {
            if (trim($candidate) === '') {
                continue;
            }

            $line = $candidate;
            break;
        }

        if ($line === null) {
            return ';';
        }

        $delimiters = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($delimiters);
        rewind($handle);

        return array_key_first($delimiters) ?: ';';
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = str_replace("\u{FEFF}", '', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return $value;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<string, string>  $headerMap
     * @return array{
     *     start_date:string,
     *     card_code:string,
     *     vehicle_plate:string,
     *     id_usage:string,
     *     station_id:string,
     *     energy_kwh:string,
     *     net_amount:string,
     *     gross_amount:string
     * }
     */
    private function resolveColumnMap(array $headers, array $headerMap): array
    {
        $startDate = $this->findExactHeader($headers, ['StartDate']);
        $cardCode = $this->findExactHeader($headers, ['CardCode']);
        $vehiclePlate = $this->findExactHeader($headers, ['MobileRegistration']);
        $idUsage = $this->findExactHeader($headers, ['IdUsage']);
        $stationId = $this->findExactHeader($headers, ['IdChargingStation']);
        $energy = $this->findExactHeader($headers, ['Energy']);
        $netAmount = $this->findExactHeader($headers, ['ChargingTotalValue']);
        $grossAmount = $this->findExactHeader($headers, ['TotalValueWithTaxes']);

        if (! $startDate || ! $cardCode || ! $vehiclePlate || ! $idUsage) {
            throw new RuntimeException('CSV PRIO invalido: colunas obrigatorias em falta.');
        }

        return [
            'start_date' => $startDate,
            'card_code' => $cardCode,
            'vehicle_plate' => $vehiclePlate,
            'id_usage' => $idUsage,
            'station_id' => $stationId ?? 'missing_station',
            'energy_kwh' => $energy ?? 'missing_energy',
            'net_amount' => $netAmount ?? 'missing_net',
            'gross_amount' => $grossAmount ?? 'missing_gross',
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $candidates
     */
    private function findExactHeader(array $headers, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $normalizedCandidate = $this->normalizeHeader($candidate);
            foreach ($headers as $header) {
                if ($header === $normalizedCandidate) {
                    return $header;
                }
            }
        }

        return null;
    }

    private function parseAmount(mixed $value, int $precision = 2): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = preg_replace('/[^\d,.\-]/', '', (string) $value) ?? '';

        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
        }

        $raw = str_replace(',', '.', $raw);

        return round((float) $raw, $precision);
    }

    private function parseDate(string $value): Carbon
    {
        $value = trim(str_replace("\u{FEFF}", '', $value));

        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'Y-m-d',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);

                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return Carbon::parse($value);
    }

    private function normalizeNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{plates: array<string, list<int>>, cards: array<string, list<int>>}
     */
    private function buildVehicleMaps(): array
    {
        $vehicles = Vehicle::query()
            ->get(['id', 'license_plate', 'prio_card_code']);

        $plates = [];
        $cards = [];

        foreach ($vehicles as $vehicle) {
            if ($vehicle->license_plate) {
                $key = $this->normalizePlate($vehicle->license_plate);
                $plates[$key][] = $vehicle->id;
            }

            if ($vehicle->prio_card_code) {
                $key = $this->normalizeCard($vehicle->prio_card_code);
                $cards[$key][] = $vehicle->id;
            }
        }

        return [
            'plates' => $plates,
            'cards' => $cards,
        ];
    }

    private function normalizePlate(string $value): string
    {
        return strtoupper(str_replace(['-', ' '], '', trim($value)));
    }

    private function normalizeCard(string $value): string
    {
        return strtoupper(trim($value));
    }

    private function resolveVehicleId(array $maps, ?string $plate, ?string $cardCode): ?int
    {
        if ($plate) {
            $key = $this->normalizePlate($plate);
            $ids = $maps['plates'][$key] ?? [];
            if (count($ids) === 1) {
                return $ids[0];
            }
        }

        if ($cardCode) {
            $key = $this->normalizeCard($cardCode);
            $ids = $maps['cards'][$key] ?? [];
            if (count($ids) === 1) {
                return $ids[0];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function loadAllocations(array $rows, ?Carbon $min, ?Carbon $max): array
    {
        if (! $min || ! $max) {
            return [];
        }

        $vehicleIds = collect($rows)
            ->pluck('vehicle_id')
            ->filter()
            ->unique()
            ->values();

        if ($vehicleIds->isEmpty()) {
            return [];
        }

        return VehicleAllocation::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('starts_at', '<=', $max)
            ->where(function ($query) use ($min): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $min);
            })
            ->orderBy('starts_at')
            ->get()
            ->groupBy('vehicle_id')
            ->all();
    }

    private function resolveDriverForAllocation(Collection $allocations, Carbon $occurredAt): ?int
    {
        $matches = $allocations->filter(function (VehicleAllocation $allocation) use ($occurredAt): bool {
            $start = $allocation->starts_at instanceof Carbon ? $allocation->starts_at : Carbon::parse($allocation->starts_at);
            $end = $allocation->ends_at
                ? ($allocation->ends_at instanceof Carbon ? $allocation->ends_at : Carbon::parse($allocation->ends_at))
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
            return $matches->first()->driver_id;
        }

        return null;
    }

    private function resolveDriverStatus(Collection $allocations, Carbon $occurredAt): string
    {
        $matches = $allocations->filter(function (VehicleAllocation $allocation) use ($occurredAt): bool {
            $start = $allocation->starts_at instanceof Carbon ? $allocation->starts_at : Carbon::parse($allocation->starts_at);
            $end = $allocation->ends_at
                ? ($allocation->ends_at instanceof Carbon ? $allocation->ends_at : Carbon::parse($allocation->ends_at))
                : null;

            if ($start->gt($occurredAt)) {
                return false;
            }

            if ($end && $end->lt($occurredAt)) {
                return false;
            }

            return true;
        })->values();

        if ($matches->count() > 1) {
            return 'ambiguous_driver';
        }

        return 'unassigned_driver';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $columnMap
     * @param  array<string, string>  $headerMap
     * @return array<string, mixed>
     */
    private function buildRawRow(array $row, array $columnMap, array $headerMap): array
    {
        $payload = [];

        foreach ($columnMap as $key => $header) {
            if (str_starts_with($header, 'missing_')) {
                continue;
            }

            $payload[$headerMap[$header] ?? $header] = $row[$header] ?? null;
        }

        return $payload;
    }
}
