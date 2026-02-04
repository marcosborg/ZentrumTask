<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\DriverAdjustment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DriverAdjustmentsCsvImportService
{
    /**
     * @return array{
     *     total:int,
     *     inserted:int,
     *     updated:int,
     *     skipped:int,
     *     invalid_rows:int,
     *     missing_drivers:list<string>
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

        $columnMap = $this->resolveColumnMap($headers);
        $missingDrivers = [];

        $parsedRows = [];
        $invalidRows = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $driverKey = $this->normalizeNullable($row[$columnMap['driver']] ?? null);
            $dateValue = $row[$columnMap['date']] ?? null;
            $amountRaw = $row[$columnMap['amount']] ?? null;

            if (! $driverKey || ! $dateValue || ! $amountRaw) {
                $invalidRows++;
                $skipped++;

                continue;
            }

            $driver = $this->resolveDriver((string) $driverKey);

            if (! $driver) {
                $missingDrivers[] = (string) $driverKey;
                $skipped++;

                continue;
            }

            $startsAt = $this->parseDate((string) $dateValue);
            $amount = $this->parseAmount($amountRaw);

            if ($amount === null) {
                $invalidRows++;
                $skipped++;

                continue;
            }

            $category = $this->normalizeCategory($this->normalizeNullable($row[$columnMap['category']] ?? null));
            $description = $this->normalizeNullable($row[$columnMap['description']] ?? null) ?? $category;
            $weeksValue = $this->normalizeNullable($row[$columnMap['weeks']] ?? null);
            $recurrenceWeeks = $weeksValue ? max(1, (int) $weeksValue) : null;
            if ($recurrenceWeeks === 1) {
                $recurrenceWeeks = null;
            }

            $externalRef = $this->buildExternalRef($driver->id, $startsAt, $amount, $description, $category, $recurrenceWeeks);

            $parsedRows[] = [
                'driver_id' => $driver->id,
                'starts_at' => $startsAt,
                'recurrence_weeks' => $recurrenceWeeks,
                'category' => $category,
                'description' => $description,
                'amount' => $amount,
                'external_ref' => $externalRef,
                'raw_row' => $this->buildRawRow($row, $columnMap, $headerMap),
            ];
        }

        $inserted = 0;
        $updated = 0;

        DB::transaction(function () use ($parsedRows, $path, &$inserted, &$updated): void {
            foreach ($parsedRows as $row) {
                $model = DriverAdjustment::query()->updateOrCreate(
                    [
                        'driver_id' => $row['driver_id'],
                        'external_ref' => $row['external_ref'],
                    ],
                    [
                        'starts_at' => $row['starts_at'],
                        'recurrence_weeks' => $row['recurrence_weeks'],
                        'category' => $row['category'],
                        'description' => $row['description'],
                        'amount' => $row['amount'],
                        'raw_row' => $row['raw_row'],
                        'source_file' => basename($path),
                        'imported_at' => now(),
                    ]
                );

                if ($model->wasRecentlyCreated) {
                    $inserted++;
                } else {
                    $updated++;
                }
            }
        });

        return [
            'total' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'invalid_rows' => $invalidRows,
            'missing_drivers' => array_values(array_unique($missingDrivers)),
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
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '', $value) ?? '';

        return $value;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array{driver:string, date:string, amount:string, description:string, category:string, weeks:string}
     */
    private function resolveColumnMap(array $headers): array
    {
        $driver = $this->findExactHeader($headers, ['driver', 'motorista', 'email', 'driveremail', 'drivercode', 'codigomotorista']);
        $date = $this->findExactHeader($headers, ['data', 'date', 'startsat', 'inicio']);
        $amount = $this->findExactHeader($headers, ['valor', 'amount', 'montante']);
        $description = $this->findExactHeader($headers, ['descricao', 'description', 'mensagem', 'observacao', 'obs']);
        $category = $this->findExactHeader($headers, ['categoria', 'category', 'tipo']);
        $weeks = $this->findExactHeader($headers, ['semanas', 'weeks', 'recorrencia', 'recurrence']);

        $missing = [];
        if (! $driver) {
            $missing[] = 'MOTORISTA';
        }
        if (! $date) {
            $missing[] = 'DATA';
        }
        if (! $amount) {
            $missing[] = 'VALOR';
        }

        if ($missing !== []) {
            throw new RuntimeException('CSV ajustes invalido: colunas obrigatorias em falta: '.implode(', ', $missing).'.');
        }

        return [
            'driver' => $driver,
            'date' => $date,
            'amount' => $amount,
            'description' => $description ?? 'missing_description',
            'category' => $category ?? 'missing_category',
            'weeks' => $weeks ?? 'missing_weeks',
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

    private function parseDate(string $value): Carbon
    {
        $value = trim(str_replace("\u{FEFF}", '', $value));

        $formats = [
            'd/m/Y',
            'd-m-Y',
            'Y-m-d',
            'd/m/y',
            'd-m-y',
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

    private function resolveDriver(string $value): ?Driver
    {
        $normalized = strtolower(trim($value));

        $driver = Driver::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalized])
            ->first();

        if ($driver) {
            return $driver;
        }

        $driver = Driver::query()
            ->whereRaw('LOWER(TRIM(bolt_driver_code)) = ?', [$normalized])
            ->orWhereRaw('LOWER(TRIM(uber_driver_code)) = ?', [$normalized])
            ->first();

        if ($driver) {
            return $driver;
        }

        return Driver::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->first();
    }

    private function normalizeCategory(?string $value): string
    {
        if (! $value) {
            return 'acerto';
        }

        $normalized = strtolower($value);

        if (str_contains($normalized, 'cauc')) {
            return 'caucao';
        }

        if (str_contains($normalized, 'acert')) {
            return 'acerto';
        }

        return $normalized;
    }

    private function buildExternalRef(int $driverId, Carbon $startsAt, float $amount, string $description, string $category, ?int $weeks): string
    {
        return sha1($driverId.'|'.$startsAt->toDateString().'|'.$amount.'|'.$description.'|'.$category.'|'.($weeks ?? 0));
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
