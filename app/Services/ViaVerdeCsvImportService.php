<?php

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleAllocation;
use App\Models\ViaVerdeTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ViaVerdeCsvImportService
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
     *     unassigned_driver:int
     * }
     */
    public function import(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Ficheiro nao encontrado: '.$path);
        }

        $defaultPlate = $this->extractPlateFromFilename(basename($path));
        $defaultPlate = $defaultPlate ? $this->normalizePlate($defaultPlate) : null;
        $vehicleByPlate = [];

        [$rows, $headers, $headerMap] = $this->readRows($path);

        if ($rows === []) {
            throw new RuntimeException('Ficheiro sem linhas.');
        }

        $columnMap = $this->resolveColumnMap($headers, $headerMap);

        $parsedRows = [];
        $invalidRows = 0;
        $skipped = 0;
        $occurredAtMin = null;
        $occurredAtMax = null;

        foreach ($rows as $row) {
            $dateValue = $row[$columnMap['date']] ?? null;
            $timeValue = $row[$columnMap['time']] ?? null;
            $amountRaw = $row[$columnMap['amount']] ?? null;

            if (! $dateValue || ! $amountRaw) {
                $invalidRows++;
                $skipped++;

                continue;
            }

            $occurredAt = $this->parseDateTime((string) $dateValue, $timeValue ? (string) $timeValue : null);
            $amount = $this->parseAmount($amountRaw);

            if ($amount === null) {
                $invalidRows++;
                $skipped++;

                continue;
            }

            $location = $this->buildLocation($row, $columnMap);
            $typeRaw = $this->normalizeNullable($row[$columnMap['type']] ?? null);
            $type = $this->mapType($typeRaw);
            $externalRef = $this->buildExternalRef($occurredAt, $location, $amount, $type);
            $candidatePlate = $this->normalizeNullable($row[$columnMap['plate']] ?? null);
            $plate = $candidatePlate ? $this->normalizePlate($candidatePlate) : $defaultPlate;

            if (! $plate) {
                throw new RuntimeException('Nao foi possivel identificar a matricula no ficheiro Via Verde.');
            }

            $vehicle = $vehicleByPlate[$plate] ??= Vehicle::query()
                ->where('license_plate', $plate)
                ->first();

            if (! $vehicle) {
                throw new RuntimeException("Viatura nao encontrada para matricula {$plate}.");
            }

            $parsedRows[] = [
                'occurred_at' => $occurredAt,
                'vehicle_plate' => $plate,
                'location' => $location,
                'type' => $type,
                'amount' => $amount,
                'external_ref' => $externalRef,
                'vehicle_id' => $vehicle->id,
                'driver_id' => null,
                'assignment_status' => 'unassigned_driver',
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
                'unassigned_driver' => 0,
            ];
        }

        $inserted = 0;
        $updated = 0;
        $unassignedDriver = 0;
        $vehicleIds = collect($parsedRows)
            ->pluck('vehicle_id')
            ->filter()
            ->unique()
            ->values()
            ->all();
        $allocationsByVehicle = $this->loadAllocationsByVehicle(
            $vehicleIds,
            $occurredAtMin,
            $occurredAtMax
        );

        DB::transaction(function () use ($parsedRows, $path, $allocationsByVehicle, &$inserted, &$updated, &$unassignedDriver): void {
            foreach ($parsedRows as $row) {
                $match = $this->resolveDriverForTransaction(
                    $allocationsByVehicle->get((int) $row['vehicle_id'], collect()),
                    $row['occurred_at']
                );

                $model = ViaVerdeTransaction::query()->updateOrCreate(
                    [
                        'vehicle_id' => $row['vehicle_id'],
                        'external_ref' => $row['external_ref'],
                    ],
                    [
                        'occurred_at' => $row['occurred_at'],
                        'vehicle_plate' => $row['vehicle_plate'],
                        'location' => $row['location'],
                        'type' => $row['type'],
                        'amount' => $row['amount'],
                        'driver_id' => $match['driver_id'],
                        'assignment_status' => $match['status'],
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

                if ($match['status'] !== 'ok') {
                    $unassignedDriver++;
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
            'unassigned_driver' => $unassignedDriver,
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

            $normalizedCandidate = array_map(
                fn ($value) => $this->normalizeHeader((string) $value),
                $candidate
            );

            if ($this->isLikelyHeader($normalizedCandidate)) {
                $header = $candidate;
                break;
            }
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
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, string>, 2: array<string, string>}
     */
    private function readXlsx(string $path): array
    {
        try {
            $archive = new \PharData($path);
        } catch (Throwable $exception) {
            throw new RuntimeException('Nao foi possivel abrir o ficheiro XLSX.');
        }

        $sharedStrings = $this->readXlsxSharedStrings($archive);
        $sheetXml = $this->readFirstWorksheetXml($archive);

        if ($sheetXml === null) {
            throw new RuntimeException('XLSX sem folhas.');
        }

        $rowsRaw = $this->parseXlsxSheetRows($sheetXml, $sharedStrings);

        if ($rowsRaw === []) {
            throw new RuntimeException('XLSX sem linhas.');
        }

        $header = null;
        $dataRows = [];

        foreach ($rowsRaw as $row) {
            $hasContent = count(array_filter($row, fn ($value): bool => trim((string) $value) !== '')) > 0;

            if (! $hasContent) {
                continue;
            }

            if ($header === null) {
                $normalizedCandidate = array_map(
                    fn ($value) => $this->normalizeHeader((string) $value),
                    $row
                );

                if ($this->isLikelyHeader($normalizedCandidate)) {
                    $header = $row;

                    continue;
                }
            }

            if ($header !== null) {
                $dataRows[] = $row;
            }
        }

        if (! is_array($header)) {
            throw new RuntimeException('XLSX sem cabecalho.');
        }

        $normalizedHeader = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);
        $normalizedToOriginal = [];
        foreach ($header as $index => $original) {
            $normalized = $normalizedHeader[$index] ?? 'col_'.$index;
            if (! array_key_exists($normalized, $normalizedToOriginal)) {
                $normalizedToOriginal[$normalized] = (string) $original;
            }
        }

        $rows = [];
        foreach ($dataRows as $data) {
            $row = [];
            $max = max(count($data), count($normalizedHeader));

            for ($index = 0; $index < $max; $index++) {
                $key = $normalizedHeader[$index] ?? 'col_'.$index;
                $value = $data[$index] ?? null;
                $row[$key] = is_string($value) ? trim($value) : $value;
            }

            if (count($row) === 1 && ($row['col_0'] ?? '') === '') {
                continue;
            }

            $rows[] = $row;
        }

        return [$rows, $normalizedHeader, $normalizedToOriginal];
    }

    /**
     * @return array<int, string>
     */
    private function readXlsxSharedStrings(\PharData $archive): array
    {
        $xml = $this->readArchiveFile($archive, 'xl/sharedStrings.xml');

        if ($xml === null || trim($xml) === '') {
            return [];
        }

        $document = @simplexml_load_string($xml);

        if ($document === false) {
            return [];
        }

        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $document->registerXPathNamespace('s', $namespace);

        $values = [];
        $nodes = $document->xpath('//s:si') ?: [];

        foreach ($nodes as $node) {
            $parts = $node->xpath('.//*[local-name()="t"]') ?: [];
            $text = collect($parts)
                ->map(fn ($part): string => (string) $part)
                ->implode('');
            $values[] = $text;
        }

        return $values;
    }

    private function readFirstWorksheetXml(\PharData $archive): ?string
    {
        return $this->readArchiveFile($archive, 'xl/worksheets/sheet1.xml');
    }

    private function readArchiveFile(\PharData $archive, string $internalPath): ?string
    {
        $pharPath = 'phar://'.str_replace('\\', '/', $archive->getPath()).'/'.$internalPath;
        $contents = @file_get_contents($pharPath);

        return $contents === false ? null : $contents;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<int, string>>
     */
    private function parseXlsxSheetRows(string $sheetXml, array $sharedStrings): array
    {
        $document = @simplexml_load_string($sheetXml);

        if ($document === false) {
            return [];
        }

        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $document->registerXPathNamespace('s', $namespace);

        $rows = [];
        $rowNodes = $document->xpath('//s:sheetData/s:row') ?: [];

        foreach ($rowNodes as $rowNode) {
            $columns = [];
            $maxColumn = -1;
            $cells = $rowNode->xpath('./*[local-name()="c"]') ?: [];

            foreach ($cells as $cell) {
                $reference = (string) ($cell['r'] ?? '');
                $columnIndex = $this->columnReferenceToIndex($reference);

                if ($columnIndex === null) {
                    continue;
                }

                $type = (string) ($cell['t'] ?? '');
                $value = $this->extractXlsxCellValue($cell, $type, $sharedStrings);
                $columns[$columnIndex] = $value;
                $maxColumn = max($maxColumn, $columnIndex);
            }

            if ($maxColumn < 0) {
                $rows[] = [];

                continue;
            }

            $row = [];
            for ($column = 0; $column <= $maxColumn; $column++) {
                $row[] = $columns[$column] ?? '';
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     */
    private function extractXlsxCellValue(\SimpleXMLElement $cell, string $type, array $sharedStrings): string
    {
        if ($type === 'inlineStr') {
            $nodes = $cell->xpath('./*[local-name()="is"]/*[local-name()="t"]') ?: [];

            return collect($nodes)
                ->map(fn ($node): string => (string) $node)
                ->implode('');
        }

        $raw = trim((string) ($cell->v ?? ''));

        if ($raw === '') {
            return '';
        }

        if ($type === 's') {
            $index = (int) $raw;

            return (string) ($sharedStrings[$index] ?? '');
        }

        if ($type === 'b') {
            return $raw === '1' ? '1' : '0';
        }

        return $raw;
    }

    private function columnReferenceToIndex(string $reference): ?int
    {
        if (! preg_match('/^([A-Z]+)\d+$/i', $reference, $matches)) {
            return null;
        }

        $letters = strtoupper($matches[1]);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
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

    /**
     * @param  array<int, string>  $normalizedHeader
     */
    private function isLikelyHeader(array $normalizedHeader): bool
    {
        $set = array_filter($normalizedHeader);

        $hasDate = in_array('data', $set, true) || in_array('entrydate', $set, true);
        $hasTime = in_array('hora', $set, true) || in_array('entrydate', $set, true);
        $hasAmount = in_array('valor', $set, true)
            || in_array('value', $set, true)
            || in_array('liquidvalue', $set, true)
            || in_array('total', $set, true)
            || in_array('totaleur', $set, true);

        return $hasDate && $hasTime && $hasAmount;
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
     * @param  array<string, string>  $headerMap
     * @return array{date:string, time:string, location:string, type:string, amount:string}
     */
    private function resolveColumnMap(array $headers, array $headerMap): array
    {
        $date = $this->findExactHeader($headers, [
            'DATA',
            'Data',
            'Data Movimento',
            'DataMovimento',
            'Entry Date',
            'EntryDate',
            'Exit Date',
            'ExitDate',
        ]);
        $time = $this->findExactHeader($headers, [
            'HORA',
            'Hora',
            'Hora Movimento',
            'HoraMovimento',
        ]);
        $location = $this->findExactHeader($headers, ['LOCAL', 'Local']);
        $entryPoint = $this->findExactHeader($headers, ['Entry Point', 'EntryPoint', 'Entrada']);
        $exitPoint = $this->findExactHeader($headers, ['Exit Point', 'ExitPoint', 'Saida', 'Saída', 'Sada']);
        $type = $this->findExactHeader($headers, ['TIPO', 'Tipo', 'Service Description', 'ServiceDescription', 'Market Description', 'MarketDescription']);
        $liquidAmount = $this->findExactHeader($headers, ['Liquid Value', 'LiquidValue', 'Valor S/ IVA', 'Valor Sem IVA', 'ValorSemIVA']);
        $valueAmount = $this->findExactHeader($headers, [
            'VALOR',
            'Valor',
            'Value',
            'TOTAL',
            'Total',
            'Total (€)',
            'Total EUR',
        ]);
        $amount = $liquidAmount ?? $valueAmount;
        $plate = $this->findExactHeader($headers, ['License Plate', 'LicensePlate', 'Matricula', 'MATRICULA', 'Matrcula']);

        $missing = [];
        if (! $date) {
            $missing[] = 'DATA';
        }
        if (! $amount) {
            $missing[] = 'VALOR';
        }

        if ($missing !== []) {
            throw new RuntimeException('CSV Via Verde invalido: colunas obrigatorias em falta: '.implode(', ', $missing).'.');
        }

        return [
            'date' => $date,
            'time' => $time ?? 'missing_time',
            'location' => $location ?? 'missing_location',
            'entry_point' => $entryPoint ?? 'missing_entry',
            'exit_point' => $exitPoint ?? 'missing_exit',
            'type' => $type ?? 'missing_type',
            'amount' => $amount,
            'plate' => $plate ?? 'missing_plate',
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

    public function parseDateTime(string $date, ?string $time): Carbon
    {
        $date = trim(str_replace("\u{FEFF}", '', $date));
        $time = $time ? trim($time) : null;

        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            'd-m-Y H:i:s',
            'd-m-Y H:i',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd-m-y H:i:s',
            'd-m-y H:i',
            'd/m/y H:i:s',
            'd/m/y H:i',
        ];

        foreach ($formats as $format) {
            try {
                $value = $time ? "{$date} {$time}" : $date;
                $parsed = Carbon::createFromFormat($format, $value);

                if ($parsed !== false) {
                    return $parsed;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return Carbon::parse($time ? "{$date} {$time}" : $date);
    }

    /**
     * @param  array<string, mixed>  $rawRow
     */
    public function extractOccurredAtFromRawRow(array $rawRow): ?Carbon
    {
        $normalizedRow = [];

        foreach ($rawRow as $key => $value) {
            $normalizedRow[$this->normalizeHeader((string) $key)] = is_string($value) ? trim($value) : $value;
        }

        $date = $this->normalizeNullable($normalizedRow['entrydate'] ?? $normalizedRow['data'] ?? $normalizedRow['datamovimento'] ?? $normalizedRow['exitdate'] ?? null);
        $time = $this->normalizeNullable($normalizedRow['hora'] ?? $normalizedRow['horamovimento'] ?? null);

        if (! $date) {
            return null;
        }

        try {
            return $this->parseDateTime($date, $time);
        } catch (Throwable) {
            return null;
        }
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
     * @param  array<int, int>  $vehicleIds
     * @return Collection<int, Collection<int, VehicleAllocation>>
     */
    private function loadAllocationsByVehicle(array $vehicleIds, ?Carbon $min, ?Carbon $max): Collection
    {
        if ($vehicleIds === [] || ! $min || ! $max) {
            return collect();
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
            ->groupBy('vehicle_id');
    }

    /**
     * @return array{driver_id: int|null, status: string}
     */
    private function resolveDriverForTransaction(Collection $allocations, Carbon $occurredAt): array
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
            return [
                'driver_id' => $matches->first()->driver_id,
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

        foreach ($columnMap as $key => $header) {
            if (str_starts_with($header, 'missing_')) {
                continue;
            }

            $payload[$headerMap[$header] ?? $header] = $row[$header] ?? null;
        }

        return $payload;
    }

    private function extractPlateFromFilename(string $filename): ?string
    {
        if (preg_match('/([A-Z0-9]{2}-[A-Z0-9]{2}-[A-Z0-9]{2})/i', $filename, $matches)) {
            return strtoupper($matches[1]);
        }

        return null;
    }

    private function mapType(?string $value): string
    {
        if (! $value) {
            return 'via_verde';
        }

        $normalized = strtolower($value);

        if (str_contains($normalized, 'estacion')) {
            return 'parking';
        }

        if (str_contains($normalized, 'port')) {
            return 'toll';
        }

        return 'via_verde';
    }

    public function buildExternalRef(Carbon $occurredAt, ?string $location, float $amount, string $type): string
    {
        return sha1($occurredAt->toIso8601String().'|'.($location ?? '').'|'.$amount.'|'.$type);
    }

    private function buildLocation(array $row, array $columnMap): ?string
    {
        $location = $this->normalizeNullable($row[$columnMap['location']] ?? null);
        $entryPoint = $this->normalizeNullable($row[$columnMap['entry_point']] ?? null);
        $exitPoint = $this->normalizeNullable($row[$columnMap['exit_point']] ?? null);

        if ($entryPoint && $exitPoint) {
            return "{$entryPoint} -> {$exitPoint}";
        }

        return $location ?? $entryPoint ?? $exitPoint;
    }

    private function normalizePlate(string $value): string
    {
        return strtoupper(str_replace(' ', '', trim($value)));
    }
}
