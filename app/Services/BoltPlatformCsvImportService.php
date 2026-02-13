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
     * 1) Explicit period_start/period_end options.
     * 2) If a date column is present, use min/max dates in the file.
     * 3) Otherwise, infer from filename (YYYYMMDD-YYYYMMDD or 20YYWww).
     *
     * @return array{
     *     total:int,
     *     inserted:int,
     *     skipped:int,
     *     duplicates:int,
     *     invalid_rows:int,
     *     period_start:string,
     *     period_end:string,
     *     driver_codes:list<string>
     * }
     */
    public function import(string $path, array $options = []): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('CSV nao encontrado: '.$path);
        }

        [$rows, $headers, $headerMap] = $this->readCsv($path);

        if ($rows === []) {
            throw new RuntimeException('CSV sem linhas.');
        }

        $columnMap = $this->resolveColumnMap($headers, $headerMap);

        $period = $this->resolvePeriod($path, $rows, $columnMap['date'] ?? null, $options);

        $inserted = 0;
        $skipped = 0;
        $duplicates = 0;
        $invalidRows = 0;
        $driverCodes = [];

        foreach ($rows as $row) {
            $driverCode = $this->normalizeNullable($row[$columnMap['driver_code']] ?? null);
            if ($driverCode === null) {
                $skipped++;
                $invalidRows++;
                Log::info('Bolt CSV skip: driver_code vazio', ['row' => $row]);

                continue;
            }
            $driverCodes[] = $driverCode;

            $netRawValue = $row[$columnMap['net_amount']] ?? null;
            $tipsRawValue = $row[$columnMap['tips_amount']] ?? null;
            $netAmount = $this->parseAmount($netRawValue);
            $tipsAmount = $this->parseAmount($tipsRawValue);

            $netAmountCheck = $this->parseAmount($netRawValue);
            $tipsAmountCheck = $this->parseAmount($tipsRawValue);

            if ($netAmount !== $netAmountCheck || $tipsAmount !== $tipsAmountCheck) {
                throw new RuntimeException('Bolt CSV invalido: valores nao correspondem as colunas de origem.');
            }

            $exists = PlatformDriverBalance::query()
                ->where('platform', 'bolt')
                ->where('driver_code', $driverCode)
                ->whereDate('period_start', $period['start'])
                ->whereDate('period_end', $period['end'])
                ->exists();

            if ($exists) {
                $skipped++;
                $duplicates++;
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
                'net_source_column' => $columnMap['net_source_label'],
                'tips_source_column' => $columnMap['tips_source_label'],
                'raw_row' => [
                    'driver_identifier_column' => $columnMap['driver_source_label'],
                    'driver_identifier_value' => $row[$columnMap['driver_code']] ?? null,
                    'net_source_column' => $columnMap['net_source_label'],
                    'net_source_value' => $netRawValue,
                    'tips_source_column' => $columnMap['tips_source_label'],
                    'tips_source_value' => $tipsRawValue,
                ],
                'source_file' => basename($path),
                'imported_at' => now(),
            ]);

            $inserted++;
        }

        return [
            'total' => count($rows),
            'inserted' => $inserted,
            'skipped' => $skipped,
            'duplicates' => $duplicates,
            'invalid_rows' => $invalidRows,
            'period_start' => $period['start']->toDateString(),
            'period_end' => $period['end']->toDateString(),
            'driver_codes' => array_values(array_unique($driverCodes)),
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
     *     driver_code:string,
     *     net_amount:string,
     *     tips_amount:string,
     *     driver_source_label:string,
     *     net_source_label:string,
     *     tips_source_label:string,
     *     date?:string
     * }
     */
    private function resolveColumnMap(array $headers, array $headerMap): array
    {
        /**
         * net_amount corresponde a "Ganhos liquidos|EUR" (Bolt CSV).
         * tips_amount corresponde a "Gorjetas dos passageiros|EUR" (Bolt CSV).
         * Estas sao as unicas colunas aceites para settlement.
         */
        $driverCandidates = [
            'Identificador do motorista',
            'Identificador individual',
        ];

        $driverColumn = $this->findExactHeader($headers, $driverCandidates);
        if ($driverColumn === null) {
            throw new RuntimeException('CSV Bolt invalido: falta coluna Identificador do motorista ou Identificador individual');
        }

        $netCandidates = [
            "Ganhos l\u{00ED}quidos|\u{20AC}",
            'Ganhos liquidos|EUR',
        ];
        $tipsCandidates = [
            "Gorjetas dos passageiros|\u{20AC}",
            'Gorjetas dos passageiros|EUR',
        ];

        $netColumn = $this->findExactHeader($headers, $netCandidates);
        if ($netColumn === null) {
            throw new RuntimeException('CSV Bolt invalido: falta coluna de ganhos liquidos.');
        }

        $tipsColumn = $this->findExactHeader($headers, $tipsCandidates);
        if ($tipsColumn === null) {
            throw new RuntimeException('CSV Bolt invalido: falta coluna de gorjetas.');
        }

        $dateColumn = $this->resolveDateColumn($headers);
        $map = [
            'driver_code' => $driverColumn,
            'net_amount' => $netColumn,
            'tips_amount' => $tipsColumn,
            'driver_source_label' => $headerMap[$driverColumn] ?? $driverColumn,
            'net_source_label' => $headerMap[$netColumn] ?? $netColumn,
            'tips_source_label' => $headerMap[$tipsColumn] ?? $tipsColumn,
        ];

        if ($dateColumn !== null) {
            $map['date'] = $dateColumn;
        }

        return $map;
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

    private function resolveDateColumn(array $headers): ?string
    {
        foreach ($headers as $header) {
            if (str_starts_with($header, 'Data') || str_starts_with($header, 'date')) {
                return $header;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{start: Carbon, end: Carbon}
     */
    private function resolvePeriod(string $path, array $rows, ?string $dateColumn, array $options): array
    {
        $periodStartInput = $options['period_start'] ?? null;
        $periodEndInput = $options['period_end'] ?? null;

        if ($periodStartInput && $periodEndInput) {
            return [
                'start' => Carbon::parse($periodStartInput)->startOfDay(),
                'end' => Carbon::parse($periodEndInput)->endOfDay(),
            ];
        }

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
