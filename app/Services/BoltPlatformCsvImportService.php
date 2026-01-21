<?php

namespace App\Services;

use App\Models\PlatformDriverBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class BoltPlatformCsvImportService
{
    /**
     * Import Bolt CSV into platform_driver_balances.
     *
     * Period resolution order:
     * 1) If a date column is present, use min/max dates in the file.
     * 2) Otherwise, infer from filename (YYYYMMDD-YYYYMMDD or 20YYWww).
     *
     * @return array{inserted:int, skipped:int, period_start:string, period_end:string}
     */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('CSV nao encontrado: '.$path);
        }

        [$rows, $headers] = $this->readCsv($path);

        if ($rows === []) {
            throw new RuntimeException('CSV sem linhas.');
        }

        $columnMap = $this->resolveColumnMap($headers);
        $this->validateColumns($headers, $columnMap);

        $period = $this->resolvePeriod($path, $rows, $columnMap['date'] ?? null);

        $inserted = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $driverCode = $this->normalizeNullable($row[$columnMap['driver_code']] ?? null);
            if ($driverCode === null) {
                $skipped++;
                Log::info('Bolt CSV skip: driver_code vazio', ['row' => $row]);

                continue;
            }

            $netAmount = $this->parseAmount($row[$columnMap['net_amount']] ?? null);
            $tipsAmount = $this->parseAmount($row[$columnMap['tips_amount']] ?? null);

            $exists = PlatformDriverBalance::query()
                ->where('platform', 'bolt')
                ->where('driver_code', $driverCode)
                ->whereDate('period_start', $period['start'])
                ->whereDate('period_end', $period['end'])
                ->exists();

            if ($exists) {
                $skipped++;
                Log::info('Bolt CSV skip: duplicate period', [
                    'driver_code' => $driverCode,
                    'period_start' => $period['start'],
                    'period_end' => $period['end'],
                ]);

                continue;
            }

            PlatformDriverBalance::query()->create([
                'platform' => 'bolt',
                'driver_code' => $driverCode,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'net_amount' => $netAmount,
                'tips_amount' => $tipsAmount,
                'source_file' => basename($path),
                'imported_at' => now(),
            ]);

            $inserted++;
        }

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'period_start' => $period['start']->toDateString(),
            'period_end' => $period['end']->toDateString(),
        ];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel abrir o CSV.');
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false || $header === [null]) {
            fclose($handle);
            throw new RuntimeException('CSV sem cabecalho.');
        }

        $normalizedHeader = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);

        $rows = [];
        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if ($data === [null]) {
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

        return [$rows, $normalizedHeader];
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = str_replace("\u{FEFF}", '', $value);
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');

        return $value;
    }

    /**
     * @return array{driver_code:string, net_amount:string, tips_amount:string, date?:string}
     */
    private function resolveColumnMap(array $headers): array
    {
        $map = [
            'driver_code' => 'identificador_do_motorista',
            'net_amount' => 'ganhos_liquidos',
            'tips_amount' => 'gorjetas_dos_passageiros',
        ];

        $dateColumn = $this->resolveDateColumn($headers);
        if ($dateColumn !== null) {
            $map['date'] = $dateColumn;
        }

        return $map;
    }

    private function resolveDateColumn(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (str_starts_with($header, 'data') || str_starts_with($header, 'date')) {
                return $header;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array{driver_code:string, net_amount:string, tips_amount:string, date?:string}  $columnMap
     */
    private function validateColumns(array $headers, array $columnMap): void
    {
        $missing = [];

        foreach (['driver_code', 'net_amount', 'tips_amount'] as $key) {
            $column = $columnMap[$key] ?? null;
            if (! $column || ! in_array($column, $headers, true)) {
                $missing[] = $column ?: $key;
            }
        }

        if ($missing !== []) {
            throw new RuntimeException('CSV sem colunas obrigatorias: '.implode(', ', $missing));
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolvePeriod(string $path, array $rows, ?string $dateColumn): array
    {
        if ($dateColumn !== null) {
            $dates = [];
            foreach ($rows as $row) {
                $value = $row[$dateColumn] ?? null;
                if (! is_string($value) || trim($value) === '') {
                    continue;
                }

                try {
                    $dates[] = $this->parseDate($value);
                } catch (Throwable) {
                    continue;
                }
            }

            if ($dates !== []) {
                $start = collect($dates)->min()->startOfDay();
                $end = collect($dates)->max()->endOfDay();

                return ['start' => $start, 'end' => $end];
            }
        }

        return $this->resolvePeriodFromFilename($path);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolvePeriodFromFilename(string $path): array
    {
        $basename = pathinfo($path, PATHINFO_BASENAME);

        if (preg_match('/\b(\d{8})-(\d{8})\b/', $basename, $matches) === 1) {
            $start = Carbon::createFromFormat('Ymd', $matches[1])->startOfDay();
            $end = Carbon::createFromFormat('Ymd', $matches[2])->endOfDay();

            return ['start' => $start, 'end' => $end];
        }

        if (preg_match('/\b(20\d{2})W(\d{2})\b/i', $basename, $matches) === 1) {
            $year = (int) $matches[1];
            $week = (int) $matches[2];

            return [
                'start' => Carbon::now()->setISODate($year, $week, 1)->startOfDay(),
                'end' => Carbon::now()->setISODate($year, $week, 7)->endOfDay(),
            ];
        }

        throw new RuntimeException('Nao foi possivel inferir o periodo a partir do ficheiro.');
    }

    private function parseAmount(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        $raw = preg_replace('/[^\d,.\-]/', '', (string) $value) ?? '';

        if ($raw === '') {
            return 0.0;
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
            'd/m/Y H:i',
            'd/m/Y H:i:s',
            'Y-m-d',
            'Y-m-d H:i',
            'Y-m-d H:i:s',
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
}
