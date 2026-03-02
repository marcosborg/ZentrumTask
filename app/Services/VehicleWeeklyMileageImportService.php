<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\VehicleWeeklyMileage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Throwable;

class VehicleWeeklyMileageImportService
{
    /**
     * @return array{
     *     total:int,
     *     inserted:int,
     *     updated:int,
     *     skipped:int,
     *     invalid_rows:int,
     *     missing_plates:list<string>,
     *     unassigned_driver:int,
     *     ambiguous_driver:int,
     *     period_start:string,
     *     period_end:string
     * }
     */
    public function import(string $path, string $periodStart, string $periodEnd): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Ficheiro nao encontrado: '.$path);
        }

        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->endOfDay();

        if ($end->lt($start)) {
            throw new RuntimeException('Periodo invalido: fim anterior ao inicio.');
        }

        [$rows, $headers, $headerMap] = $this->readRows($path);

        if ($rows === []) {
            throw new RuntimeException('Ficheiro sem linhas.');
        }

        $columnMap = $this->resolveColumnMap($headers);
        $missingPlates = [];
        $invalidRows = 0;
        $skipped = 0;
        $parsedRows = [];

        foreach ($rows as $row) {
            $plateRaw = $this->normalizeNullable($row[$columnMap['plate']] ?? null);
            $weeklyKmRaw = $this->normalizeNullable($row[$columnMap['weekly_km']] ?? null);

            if (! $plateRaw || ! $weeklyKmRaw) {
                $invalidRows++;
                $skipped++;

                continue;
            }

            $weeklyKm = $this->parseAmount($weeklyKmRaw);

            if ($weeklyKm === null || $weeklyKm < 0) {
                $invalidRows++;
                $skipped++;

                continue;
            }

            $normalizedPlate = $this->normalizePlate($plateRaw);
            $vehicle = $this->resolveVehicleByPlate($normalizedPlate);

            if (! $vehicle) {
                $missingPlates[] = strtoupper($plateRaw);
                $skipped++;

                continue;
            }

            $parsedRows[] = [
                'vehicle_id' => $vehicle->id,
                'weekly_km' => round($weeklyKm, 2),
                'raw_row' => $this->buildRawRow($row, $columnMap, $headerMap),
            ];
        }

        $vehicleIds = collect($parsedRows)->pluck('vehicle_id')->unique()->values()->all();
        $allocationsByVehicle = $this->loadAllocationsByVehicle($vehicleIds, $start, $end);
        $inserted = 0;
        $updated = 0;
        $unassignedDriver = 0;
        $ambiguousDriver = 0;

        DB::transaction(function () use (
            $parsedRows,
            $allocationsByVehicle,
            $start,
            $end,
            $path,
            &$inserted,
            &$updated,
            &$unassignedDriver,
            &$ambiguousDriver
        ): void {
            foreach ($parsedRows as $row) {
                $driverMatch = $this->resolveDriverForPeriod(
                    $allocationsByVehicle->get((int) $row['vehicle_id'], collect()),
                    $start,
                    $end
                );

                $model = VehicleWeeklyMileage::query()->updateOrCreate(
                    [
                        'vehicle_id' => $row['vehicle_id'],
                        'period_start' => $start->toDateString(),
                        'period_end' => $end->toDateString(),
                    ],
                    [
                        'driver_id' => $driverMatch['driver_id'],
                        'weekly_km' => $row['weekly_km'],
                        'assignment_status' => $driverMatch['status'],
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

                if ($driverMatch['status'] === 'unassigned_driver') {
                    $unassignedDriver++;
                } elseif ($driverMatch['status'] === 'ambiguous_driver') {
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
            'missing_plates' => array_values(array_unique($missingPlates)),
            'unassigned_driver' => $unassignedDriver,
            'ambiguous_driver' => $ambiguousDriver,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<string, string>}
     */
    private function readRows(string $path): array
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'xlsx') {
            return $this->readXlsx($path);
        }

        return $this->readCsv($path);
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

        $normalizedHeader = array_map(fn ($value): string => $this->normalizeHeader((string) $value), $header);
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

            $rows[] = $row;
        }

        fclose($handle);

        return [$rows, $normalizedHeader, $normalizedToOriginal];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<string, string>}
     */
    private function readXlsx(string $path): array
    {
        if (! class_exists(IOFactory::class)) {
            throw new RuntimeException('Import XLSX indisponivel no servidor.');
        }

        try {
            $spreadsheet = IOFactory::load($path);
        } catch (Throwable $exception) {
            throw new RuntimeException('Nao foi possivel ler o XLSX: '.$exception->getMessage(), 0, $exception);
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rowsRaw = $sheet->toArray(null, true, true, true);

        if ($rowsRaw === []) {
            throw new RuntimeException('XLSX sem linhas.');
        }

        $headerRow = null;
        $headerIndex = null;

        foreach ($rowsRaw as $index => $cells) {
            $values = array_values($cells);
            $hasContent = count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) > 0;

            if (! $hasContent) {
                continue;
            }

            $headerRow = array_map(fn ($value): string => (string) $value, $values);
            $headerIndex = $index;
            break;
        }

        if (! is_array($headerRow) || $headerIndex === null) {
            throw new RuntimeException('XLSX sem cabecalho.');
        }

        $normalizedHeader = array_map(fn ($value): string => $this->normalizeHeader($value), $headerRow);
        $normalizedToOriginal = [];

        foreach ($headerRow as $index => $original) {
            $normalized = $normalizedHeader[$index] ?? 'col_'.$index;

            if (! array_key_exists($normalized, $normalizedToOriginal)) {
                $normalizedToOriginal[$normalized] = $original;
            }
        }

        $rows = [];

        foreach ($rowsRaw as $index => $cells) {
            if ($index <= $headerIndex) {
                continue;
            }

            $values = array_values($cells);
            $hasContent = count(array_filter($values, fn ($value): bool => trim((string) $value) !== '')) > 0;

            if (! $hasContent) {
                continue;
            }

            $row = [];

            foreach ($values as $valueIndex => $value) {
                $key = $normalizedHeader[$valueIndex] ?? 'col_'.$valueIndex;
                $row[$key] = is_string($value) ? trim($value) : $value;
            }

            $rows[] = $row;
        }

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

        if (function_exists('iconv')) {
            $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);

            if ($converted !== false) {
                $value = $converted;
            }
        } else {
            $value = Str::ascii($value);
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';

        return $value;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{plate:string, weekly_km:string}
     */
    private function resolveColumnMap(array $headers): array
    {
        $plate = $this->findExactHeader($headers, ['matricula', 'plate', 'vehicleplate', 'licenceplate', 'licenseplate']);
        $weeklyKm = $this->findExactHeader($headers, ['kmsemana', 'kmdasemana', 'kmweek', 'weeklykm', 'kms', 'km']);

        $missing = [];

        if (! $plate) {
            $missing[] = 'MATRICULA';
        }

        if (! $weeklyKm) {
            $missing[] = 'KM_SEMANA';
        }

        if ($missing !== []) {
            throw new RuntimeException('Import km extra invalido: colunas obrigatorias em falta: '.implode(', ', $missing).'.');
        }

        return [
            'plate' => $plate,
            'weekly_km' => $weeklyKm,
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

    private function normalizeNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseAmount(mixed $value): ?float
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

        return round((float) $raw, 2);
    }

    private function normalizePlate(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Z0-9]/', '', Str::ascii($value)) ?? '');
    }

    private function resolveVehicleByPlate(string $normalizedPlate): ?Vehicle
    {
        if ($normalizedPlate === '') {
            return null;
        }

        return Vehicle::query()
            ->whereRaw("UPPER(REPLACE(REPLACE(REPLACE(license_plate, '-', ''), ' ', ''), '.', '')) = ?", [$normalizedPlate])
            ->first();
    }

    /**
     * @param  list<int>  $vehicleIds
     * @return Collection<int, Collection<int, VehicleAllocation>>
     */
    private function loadAllocationsByVehicle(array $vehicleIds, Carbon $start, Carbon $end): Collection
    {
        if ($vehicleIds === []) {
            return collect();
        }

        return VehicleAllocation::query()
            ->whereIn('vehicle_id', $vehicleIds)
            ->where('starts_at', '<=', $end)
            ->where(function ($query) use ($start): void {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $start);
            })
            ->orderBy('starts_at')
            ->get()
            ->groupBy('vehicle_id');
    }

    /**
     * @param  Collection<int, VehicleAllocation>  $allocations
     * @return array{driver_id:int|null,status:string}
     */
    private function resolveDriverForPeriod(Collection $allocations, Carbon $start, Carbon $end): array
    {
        $matches = $allocations
            ->filter(function (VehicleAllocation $allocation) use ($start, $end): bool {
                $allocationStart = $allocation->starts_at instanceof Carbon ? $allocation->starts_at : Carbon::parse($allocation->starts_at);
                $allocationEnd = $allocation->ends_at
                    ? ($allocation->ends_at instanceof Carbon ? $allocation->ends_at : Carbon::parse($allocation->ends_at))
                    : null;

                if ($allocationStart->gt($end)) {
                    return false;
                }

                if ($allocationEnd && $allocationEnd->lt($start)) {
                    return false;
                }

                return true;
            })
            ->pluck('driver_id')
            ->filter()
            ->unique()
            ->values();

        if ($matches->count() === 1) {
            return [
                'driver_id' => (int) $matches->first(),
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

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $columnMap
     * @param  array<string, string>  $headerMap
     * @return array<string, mixed>
     */
    private function buildRawRow(array $row, array $columnMap, array $headerMap): array
    {
        $payload = [];

        foreach ($columnMap as $header) {
            $payload[$headerMap[$header] ?? $header] = $row[$header] ?? null;
        }

        return $payload;
    }
}
