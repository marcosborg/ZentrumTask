<?php

namespace App\Services;

use App\Models\Driver;
use App\Models\UberDriverEarning;
use App\Models\UberSyncRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class UberCsvImportService
{
    public function __construct(public BoltWeekService $weekService) {}

    /**
     * @param  array{
     *   columns?: array<string, string>,
     *   matchers?: list<string>,
     *   delimiter?: string,
     *   has_header?: bool
     * }  $options
     */
    public function import(string $path, array $options = []): UberSyncRun
    {
        if (! is_file($path)) {
            throw new RuntimeException('CSV nao encontrado: '.$path);
        }

        $columns = $options['columns'] ?? [];
        $matchers = $options['matchers'] ?? ['id', 'email', 'name'];
        $delimiter = $options['delimiter'] ?? $this->detectDelimiter($path);
        $hasHeader = $options['has_header'] ?? true;

        $defaults = [
            'driver_name' => 'driver_name',
            'driver_first_name' => 'driver_first_name',
            'driver_last_name' => 'driver_last_name',
            'driver_email' => 'driver_email',
            'driver_identifier' => 'driver_id',
            'uber_driver_uuid' => 'driver_uuid',
            'uber_individual_uuid' => 'individual_uuid',
            'date' => 'date',
            'gross_total' => 'gross_total',
            'gross_app' => 'gross_app',
            'gross_cash' => 'gross_cash',
            'net_total' => 'net_total',
            'expected_payment' => 'expected_payment',
            'cash_collected' => 'cash_collected',
            'tips' => 'tips',
            'commissions' => 'commissions',
            'total_fees' => 'total_fees',
            'reservation_fees' => 'reservation_fees',
            'other_fees' => 'other_fees',
            'passenger_refunds' => 'passenger_refunds',
            'expense_reimbursements' => 'expense_reimbursements',
            'tolls' => 'tolls',
            'campaign_earnings' => 'campaign_earnings',
            'vat_app' => 'vat_app',
            'vat_cash' => 'vat_cash',
            'vat_cancellation' => 'vat_cancellation',
            'vat_reservation' => 'vat_reservation',
            'currency' => 'currency',
        ];

        $columns = array_merge($defaults, $columns);

        $syncRun = UberSyncRun::query()->create([
            'source_path' => $path,
            'status' => 'running',
            'started_at' => now(),
            'row_count' => 0,
        ]);

        $errors = [];

        try {
            $rows = $this->readCsv($path, $delimiter, $hasHeader);

            if ($rows === []) {
                throw new RuntimeException('CSV sem linhas.');
            }

            $rowKeys = array_keys($rows[0]);
            $columnMap = $this->resolveColumnMap($rowKeys, $columns);
            $hasDateColumn = ! empty($columnMap['date']) && in_array($columnMap['date'], $rowKeys, true);
            $fallbackWeek = $this->resolveFallbackWeek($path, $options);
            $this->validateColumns($rowKeys, $columnMap, $hasDateColumn, $fallbackWeek !== null);

            $aggregated = [];
            $rowCount = 0;
            $totalGross = 0.0;

            foreach ($rows as $row) {
                $rowCount++;

                $dateValue = $row[$columnMap['date']] ?? null;

                if (! $dateValue && ! $fallbackWeek) {
                    $errors[] = 'Linha sem data: '.json_encode($row);

                    continue;
                }

                if ($dateValue) {
                    $week = $this->weekService->calculateWeek($this->parseDate($dateValue));
                    $weekStartDate = $week['week_start'];
                    $weekEndDate = $week['week_end'];
                } else {
                    $weekStartDate = $fallbackWeek['week_start'];
                    $weekEndDate = $fallbackWeek['week_end'];
                }

                $driverName = $this->normalizeNullable($row[$columnMap['driver_name']] ?? null);
                $driverFirstName = $this->normalizeNullable($row[$columnMap['driver_first_name']] ?? null);
                $driverLastName = $this->normalizeNullable($row[$columnMap['driver_last_name']] ?? null);
                $driverEmail = $this->normalizeNullable($row[$columnMap['driver_email']] ?? null);
                $driverIdentifier = $this->normalizeNullable($row[$columnMap['driver_identifier']] ?? null);
                $driverUuid = $this->normalizeNullable($row[$columnMap['uber_driver_uuid']] ?? null);
                $individualUuid = $this->normalizeNullable($row[$columnMap['uber_individual_uuid']] ?? null);
                $currency = $this->normalizeNullable($row[$columnMap['currency']] ?? null) ?? 'EUR';

                if (! $driverName && ($driverFirstName || $driverLastName)) {
                    $driverName = trim(($driverFirstName ?? '').' '.($driverLastName ?? '')) ?: null;
                }

                $driver = $this->matchDriver($driverUuid, $driverEmail, $driverName, $matchers);

                $key = implode('|', [
                    $driverUuid ?? 'no-uuid',
                    $individualUuid ?? 'no-individual',
                    $weekStartDate->toDateString(),
                    $weekEndDate->toDateString(),
                ]);

                if (! isset($aggregated[$key])) {
                    $aggregated[$key] = [
                        'uber_sync_run_id' => $syncRun->id,
                        'driver_id' => $driver?->id,
                        'uber_driver_identifier' => $driverIdentifier,
                        'uber_driver_uuid' => $driverUuid,
                        'uber_individual_uuid' => $individualUuid,
                        'uber_driver_name' => $driverName,
                        'uber_driver_email' => $driverEmail,
                        'driver_name_snapshot' => $driverName,
                        'driver_email_snapshot' => $driverEmail,
                        'driver_resolved' => $driver !== null,
                        'week_start' => $weekStartDate->toDateString(),
                        'week_end' => $weekEndDate->toDateString(),
                        'total_amount' => 0.0,
                        'gross_total' => 0.0,
                        'gross_app' => 0.0,
                        'gross_cash' => 0.0,
                        'net_total' => 0.0,
                        'expected_payment' => 0.0,
                        'cash_collected' => 0.0,
                        'tips' => 0.0,
                        'commissions' => 0.0,
                        'total_fees' => 0.0,
                        'reservation_fees' => 0.0,
                        'other_fees' => 0.0,
                        'passenger_refunds' => 0.0,
                        'expense_reimbursements' => 0.0,
                        'tolls' => 0.0,
                        'campaign_earnings' => 0.0,
                        'vat_app' => 0.0,
                        'vat_cash' => 0.0,
                        'vat_cancellation' => 0.0,
                        'vat_reservation' => 0.0,
                        'currency' => $currency ?: 'EUR',
                        'raw_payload' => [],
                    ];
                }

                $amounts = [
                    'gross_total' => $this->parseAmount($row[$columnMap['gross_total']] ?? null),
                    'gross_app' => $this->parseAmount($row[$columnMap['gross_app']] ?? null),
                    'gross_cash' => $this->parseAmount($row[$columnMap['gross_cash']] ?? null),
                    'net_total' => $this->parseAmount($row[$columnMap['net_total']] ?? null),
                    'expected_payment' => $this->parseAmount($row[$columnMap['expected_payment']] ?? null),
                    'cash_collected' => $this->parseAmount($row[$columnMap['cash_collected']] ?? null),
                    'tips' => $this->parseAmount($row[$columnMap['tips']] ?? null),
                    'commissions' => $this->parseAmount($row[$columnMap['commissions']] ?? null),
                    'total_fees' => $this->parseAmount($row[$columnMap['total_fees']] ?? null),
                    'reservation_fees' => $this->parseAmount($row[$columnMap['reservation_fees']] ?? null),
                    'other_fees' => $this->parseAmount($row[$columnMap['other_fees']] ?? null),
                    'passenger_refunds' => $this->parseAmount($row[$columnMap['passenger_refunds']] ?? null),
                    'expense_reimbursements' => $this->parseAmount($row[$columnMap['expense_reimbursements']] ?? null),
                    'tolls' => $this->parseAmount($row[$columnMap['tolls']] ?? null),
                    'campaign_earnings' => $this->parseAmount($row[$columnMap['campaign_earnings']] ?? null),
                    'vat_app' => $this->parseAmount($row[$columnMap['vat_app']] ?? null),
                    'vat_cash' => $this->parseAmount($row[$columnMap['vat_cash']] ?? null),
                    'vat_cancellation' => $this->parseAmount($row[$columnMap['vat_cancellation']] ?? null),
                    'vat_reservation' => $this->parseAmount($row[$columnMap['vat_reservation']] ?? null),
                ];

                foreach ($amounts as $field => $value) {
                    $aggregated[$key][$field] += $value;
                }

                $aggregated[$key]['total_amount'] += $amounts['net_total'] ?: $amounts['gross_total'];
                $aggregated[$key]['raw_payload'][] = $row;
                $totalGross += $amounts['gross_total'];
            }

            DB::transaction(function () use ($aggregated): void {
                $tableColumns = Schema::getColumnListing('uber_driver_earnings');
                $columnMap = array_fill_keys($tableColumns, true);

                foreach ($aggregated as $payload) {
                    $query = UberDriverEarning::query()
                        ->whereDate('week_start', $payload['week_start'])
                        ->whereDate('week_end', $payload['week_end']);

                    if (Schema::hasColumn('uber_driver_earnings', 'uber_driver_uuid')) {
                        $query->when(
                            $payload['uber_driver_uuid'],
                            fn ($builder, $value) => $builder->where('uber_driver_uuid', $value),
                            fn ($builder) => $builder->whereNull('uber_driver_uuid')
                        );
                    } else {
                        $query->when(
                            $payload['uber_driver_identifier'],
                            fn ($builder, $value) => $builder->where('uber_driver_identifier', $value),
                            fn ($builder) => $builder->whereNull('uber_driver_identifier')
                        );
                    }

                    if (Schema::hasColumn('uber_driver_earnings', 'uber_individual_uuid')) {
                        if ($payload['uber_individual_uuid']) {
                            $query->where('uber_individual_uuid', $payload['uber_individual_uuid']);
                        } else {
                            $query->whereNull('uber_individual_uuid');
                        }
                    }

                    $existing = $query->first();

                    $filteredPayload = array_intersect_key($payload, $columnMap);

                    if ($existing) {
                        $existing->fill($filteredPayload)->save();
                    } else {
                        UberDriverEarning::query()->create($filteredPayload);
                    }
                }
            });

            $syncRun->update([
                'status' => 'completed',
                'finished_at' => now(),
                'row_count' => $rowCount,
                'totals' => [
                    'rows' => $rowCount,
                    'drivers' => count($aggregated),
                    'amount' => $totalGross,
                ],
                'meta' => [
                    'errors' => $errors,
                    'delimiter' => $delimiter,
                ],
            ]);
        } catch (\Throwable $exception) {
            $syncRun->update([
                'status' => 'failed',
                'finished_at' => now(),
                'meta' => [
                    'errors' => array_merge($errors, [$exception->getMessage()]),
                ],
            ]);

            throw $exception;
        }

        return $syncRun->fresh(['earnings']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readCsv(string $path, string $delimiter, bool $hasHeader): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException('Nao foi possivel abrir o CSV.');
        }

        $header = [];
        $rows = [];

        if ($hasHeader) {
            $header = fgetcsv($handle, 0, $delimiter) ?: [];
            $header = array_map(fn ($value) => $this->normalizeHeader((string) $value), $header);
        }

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }

            $row = [];

            foreach ($data as $index => $value) {
                $key = $hasHeader ? ($header[$index] ?? 'col_'.$index) : 'col_'.$index;
                $row[$key] = is_string($value) ? trim($value) : $value;
            }

            if (count($row) === 1 && $row['col_0'] === '') {
                continue;
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function detectDelimiter(string $path): string
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return ',';
        }

        $line = fgets($handle);
        fclose($handle);

        if ($line === false) {
            return ',';
        }

        $delimiters = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];

        arsort($delimiters);

        return array_key_first($delimiters) ?: ',';
    }

    private function normalizeHeader(string $value): string
    {
        $value = trim($value);
        $value = str_replace("\u{FEFF}", '', $value);
        $value = Str::of($value)
            ->ascii()
            ->lower()
            ->replace([' ', '-', '/', '.'], '_')
            ->replace(['(', ')'], '')
            ->replace('__', '_')
            ->__toString();

        return $value;
    }

    private function parseAmount($value): float
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

        return (float) $raw;
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
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            throw new RuntimeException('Data invalida: '.$value);
        }
    }

    private function normalizeNullable($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string>  $matchers
     */
    /**
     * @param  list<string>  $matchers
     *
     * Usa o matcher "id" como alias do UUID Uber para manter a UI atual.
     */
    private function matchDriver(?string $uberUuid, ?string $email, ?string $name, array $matchers): ?Driver
    {
        foreach ($matchers as $matcher) {
            if ($matcher === 'id' && $uberUuid && Schema::hasColumn('drivers', 'uber_driver_uuid')) {
                $driver = Driver::query()->where('uber_driver_uuid', $uberUuid)->first();
                if ($driver) {
                    return $driver;
                }
            }

            if ($matcher === 'email' && $email) {
                $driver = Driver::query()->where('email', $email)->first();
                if ($driver) {
                    return $driver;
                }
            }

            if ($matcher === 'name' && $name) {
                $driver = Driver::query()->where('name', $name)->first();
                if ($driver) {
                    return $driver;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $rowKeys
     * @param  array<string, string>  $columns
     */
    private function validateColumns(array $rowKeys, array $columns, bool $hasDateColumn, bool $allowsMissingDate): void
    {
        $missing = [];

        $driverColumns = [
            $columns['uber_driver_uuid'] ?? null,
            $columns['driver_email'] ?? null,
            $columns['driver_name'] ?? null,
            $columns['driver_first_name'] ?? null,
        ];

        $hasDriverColumn = false;

        foreach ($driverColumns as $column) {
            if ($column && in_array($column, $rowKeys, true)) {
                $hasDriverColumn = true;
                break;
            }
        }

        if (! $hasDriverColumn) {
            $missing[] = 'identificador_motorista/email/nome';
        }

        if (! $hasDateColumn && ! $allowsMissingDate) {
            $missing[] = $columns['date'] ?? 'data';
        }

        $hasGrossTotal = ! empty($columns['gross_total']) && in_array($columns['gross_total'], $rowKeys, true);
        $hasNetTotal = ! empty($columns['net_total']) && in_array($columns['net_total'], $rowKeys, true);

        if (! $hasGrossTotal && ! $hasNetTotal) {
            $missing[] = 'gross_total/net_total';
        }

        if ($missing !== []) {
            throw new RuntimeException('CSV sem colunas obrigatorias: '.implode(', ', $missing));
        }
    }

    /**
     * @param  array<int, string>  $rowKeys
     * @param  array<string, string>  $columns
     * @return array<string, string>
     */
    private function resolveColumnMap(array $rowKeys, array $columns): array
    {
        $aliases = [
            'uber_driver_uuid' => ['uuid_do_motorista', 'identificador_do_motorista', 'identificador_motorista', 'driver_uuid', 'driver_id', 'motorista_uuid', 'uuid'],
            'uber_individual_uuid' => ['identificador_individual', 'individual_uuid', 'individual_id'],
            'driver_name' => ['motorista', 'nome', 'driver_name', 'driver', 'driver_name_'],
            'driver_first_name' => ['nome_proprio_do_motorista', 'nome_proprio', 'primeiro_nome', 'first_name'],
            'driver_last_name' => ['apelido_do_motorista', 'apelido', 'ultimo_nome', 'last_name', 'sobrenome'],
            'driver_email' => ['email', 'driver_email', 'e_mail'],
            'driver_identifier' => ['uuid_do_motorista', 'identificador_do_motorista', 'driver_id', 'identificador', 'driver_identifier'],
            'date' => [
                'data',
                'date',
                'data_do_pagamento',
                'data_de_pagamento',
                'payment_date',
                'trip_date',
                'service_date',
                'data_da_viagem',
                'data_viagem',
                'data_da_corrida',
                'data_corrida',
                'data_do_servico',
                'data_servico',
            ],
            'gross_total' => [
                'ganhos_brutos_total',
                'ganhos_brutos_total_',
                'ganhos_brutos_totais',
                'ganhos_brutos_total_eur',
                'ganhos_brutos_total_euro',
                'ganhos_brutos_total_euros',
                'total',
                'total_amount',
                'total_earnings',
                'gross_total',
                'gross_earnings',
                'pago_a_si',
            ],
            'gross_app' => ['ganhos_brutos_pagamentos_na_app', 'ganhos_brutos_app', 'gross_app', 'app_earnings'],
            'gross_cash' => ['ganhos_brutos_pagamentos_em_dinheiro', 'ganhos_brutos_dinheiro', 'gross_cash', 'cash_earnings'],
            'net_total' => ['ganhos_liquidos', 'ganhos_liquidos_total', 'net_total', 'net_earnings', 'pago_a_si_:_os_seus_rendimentos'],
            'expected_payment' => ['pagamento_previsto', 'expected_payment', 'payout', 'transferido_para_uma_conta_bancaria'],
            'cash_collected' => ['dinheiro_recebido', 'cash_collected', 'cash_collected_total', 'pagamentos_:_dinheiro_recebido'],
            'tips' => ['gorjetas_dos_passageiros', 'gorjetas', 'tips'],
            'commissions' => ['comissoes', 'commissions', 'service_fees', 'taxa_de_servico'],
            'total_fees' => ['total_de_taxas', 'total_fees', 'fees_total'],
            'reservation_fees' => ['taxas_de_reserva', 'reservation_fees', 'booking_fees'],
            'other_fees' => ['outras_taxas', 'other_fees'],
            'passenger_refunds' => ['reembolsos_aos_passageiros', 'passenger_refunds'],
            'expense_reimbursements' => ['reembolsos_de_despesas', 'expense_reimbursements', 'reembolsos_:_portagem', 'portagem'],
            'tolls' => ['portagens', 'tolls'],
            'campaign_earnings' => ['ganhos_da_campanha', 'campaign_earnings', 'promotions', 'aumento_de_rendimentos'],
            'vat_app' => ['iva_sobre_os_ganhos_brutos_pagamentos_na_app', 'vat_app', 'impostos'],
            'vat_cash' => ['iva_sobre_os_ganhos_brutos_pagamentos_em_dinheiro', 'vat_cash'],
            'vat_cancellation' => ['iva_das_taxas_de_cancelamento', 'vat_cancellation'],
            'vat_reservation' => ['iva_das_taxas_de_reserva', 'vat_reservation'],
            'currency' => ['moeda', 'currency', 'currency_code'],
        ];

        $normalizedColumns = [];

        foreach ($columns as $field => $column) {
            $normalizedColumns[$field] = $column ? $this->normalizeHeader($column) : $column;
        }

        $map = $normalizedColumns;

        foreach ($normalizedColumns as $field => $column) {
            $matched = false;

            if ($column && in_array($column, $rowKeys, true)) {
                $map[$field] = $column;
                $matched = true;
            }

            if (! $matched) {
                $candidates = $aliases[$field] ?? [];

                foreach ($candidates as $candidate) {
                    if (in_array($candidate, $rowKeys, true)) {
                        $map[$field] = $candidate;
                        $matched = true;
                        break;
                    }
                }
            }

            if (! $matched && $field === 'driver_name') {
                foreach ($rowKeys as $key) {
                    if (Str::contains($key, ['motorista', 'driver', 'nome'])) {
                        $map[$field] = $key;
                        $matched = true;
                        break;
                    }
                }
            }

            if (! $matched && $field === 'driver_email') {
                foreach ($rowKeys as $key) {
                    if (Str::contains($key, ['email', 'mail'])) {
                        $map[$field] = $key;
                        $matched = true;
                        break;
                    }
                }
            }

            if (! $matched && $field === 'gross_total') {
                foreach ($rowKeys as $key) {
                    if (Str::startsWith($key, 'ganhos_brutos_total') || Str::contains($key, ['pago_a_si', 'gross', 'brutos']) && Str::contains($key, ['total'])) {
                        $map[$field] = $key;
                        $matched = true;
                        break;
                    }
                }
            }

            if (! $matched && $field === 'net_total') {
                foreach ($rowKeys as $key) {
                    if (Str::contains($key, ['ganhos_liquidos', 'net_earnings', 'net_total', 'liquidos', 'os_seus_rendimentos'])) {
                        $map[$field] = $key;
                        $matched = true;
                        break;
                    }
                }
            }

            if (! $matched && $field === 'date') {
                foreach ($rowKeys as $key) {
                    if (Str::startsWith($key, 'data')) {
                        $map[$field] = $key;
                        break;
                    }
                }
            }
        }

        $expectedIndexMap = [
            'driver_name' => 0,
            'driver_email' => 1,
            'gross_total' => 3,
            'gross_app' => 4,
            'vat_app' => 5,
            'gross_cash' => 6,
            'vat_cash' => 7,
            'cash_collected' => 8,
            'tips' => 9,
            'campaign_earnings' => 10,
            'expense_reimbursements' => 11,
            'vat_cancellation' => 13,
            'tolls' => 14,
            'reservation_fees' => 15,
            'vat_reservation' => 16,
            'total_fees' => 17,
            'commissions' => 18,
            'passenger_refunds' => 19,
            'other_fees' => 20,
            'net_total' => 21,
            'expected_payment' => 22,
            'driver_identifier' => 25,
            'uber_individual_uuid' => 26,
        ];

        if (count($rowKeys) >= 27 && ($rowKeys[0] ?? null)) {
            $firstKey = $rowKeys[0];

            if (Str::contains($firstKey, ['motorista', 'driver', 'nome'])) {
                foreach ($expectedIndexMap as $field => $index) {
                    if (isset($map[$field]) && in_array($map[$field], $rowKeys, true)) {
                        continue;
                    }

                    $candidate = $rowKeys[$index] ?? null;

                    if ($candidate) {
                        $map[$field] = $candidate;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * @param  array{week_start?: string, week_end?: string, week_code?: string}  $options
     * @return array{week_start: Carbon, week_end: Carbon}|null
     */
    private function resolveFallbackWeek(string $path, array $options): ?array
    {
        $weekStartInput = $options['week_start'] ?? null;
        $weekEndInput = $options['week_end'] ?? null;

        if ($weekStartInput || $weekEndInput) {
            $week = $this->weekService->calculateWeek($this->parseDate((string) ($weekStartInput ?: $weekEndInput)));

            return [
                'week_start' => $week['week_start'],
                'week_end' => $week['week_end'],
            ];
        }

        $basename = pathinfo($path, PATHINFO_BASENAME);

        if (preg_match('/\b(20\d{2})W(\d{2})\b/i', $basename, $matches) !== 1) {
            if (preg_match('/\b(\d{8})-(\d{8})\b/', $basename, $matches) !== 1) {
                return null;
            }

            $start = Carbon::createFromFormat('Ymd', $matches[1])->startOfDay();
            $end = Carbon::createFromFormat('Ymd', $matches[2])->endOfDay();

            return [
                'week_start' => $start,
                'week_end' => $end,
            ];
        }

        $year = (int) $matches[1];
        $weekNumber = (int) $matches[2];

        return [
            'week_start' => Carbon::now()->setISODate($year, $weekNumber, 1)->startOfDay(),
            'week_end' => Carbon::now()->setISODate($year, $weekNumber, 7)->endOfDay(),
        ];
    }
}
