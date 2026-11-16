<?php

namespace App\Services;

use App\Models\PlatformDriverBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileObject;

class PopPlatformCsvImportService
{
    /**
     * @param  array{period_start?: string, period_end?: string}  $options
     * @return array{total:int, inserted:int, duplicates:int, invalid_rows:int, skipped:int, period_start:string, period_end:string, driver_codes:list<string>}
     */
    public function import(string $path, array $options = []): array
    {
        $period = Validator::make($options, [
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
        ]);
        if ($period->fails()) {
            throw new RuntimeException('Indique um período válido para o ficheiro POP.');
        }

        $file = new SplFileObject($path, 'r');
        if ($file->fread(3) !== "\xEF\xBB\xBF") {
            $file->rewind();
        }
        $file->setCsvControl(';', '"', '');
        $headers = $file->fgetcsv();
        if (array_diff(['Motorista', 'Ganhos Líquidos', 'Gorjetas'], $headers)
            || count(array_unique($headers)) !== count($headers)) {
            throw new RuntimeException('Cabeçalho POP inválido. São necessárias as colunas Motorista, Ganhos Líquidos e Gorjetas.');
        }

        return DB::transaction(fn (): array => $this->importRows(
            $file, $headers, Carbon::parse($options['period_start']), Carbon::parse($options['period_end'])
        ));
    }

    private function importRows(SplFileObject $file, array $headers, Carbon $start, Carbon $end): array
    {
        $inserted = 0;
        $duplicates = 0;
        $invalid = 0;
        $total = 0;
        $names = [];
        while (! $file->eof()) {
            $values = $file->fgetcsv();
            if ($values === [null]) {
                continue;
            }
            $total++;
            if (count($values) !== count($headers)) {
                $invalid++;

                continue;
            }
            $row = array_combine($headers, $values);
            $name = trim($row['Motorista']);
            $net = $this->amount($row['Ganhos Líquidos']);
            $tips = $this->amount($row['Gorjetas']);
            if ($name === '' || mb_strlen($name) > 255 || $net === null || $tips === null) {
                $invalid++;

                continue;
            }

            $identifier = Str::lower($name);
            if (isset($names[$identifier])) {
                throw new RuntimeException("Nome POP repetido no ficheiro: {$name}. Confirme os totais por motorista.");
            }
            $names[$identifier] = $name;
            $balance = PlatformDriverBalance::query()->firstOrCreate([
                'platform' => 'pop',
                'driver_code' => $identifier,
                'period_start' => $start,
                'period_end' => $end,
            ], [
                'net_amount' => $net,
                'tips_amount' => $tips,
                'net_source_column' => 'Ganhos Líquidos',
                'tips_source_column' => 'Gorjetas',
                'raw_row' => $row,
                'source_file' => $file->getFilename(),
                'imported_at' => now(),
            ]);

            if ($balance->wasRecentlyCreated) {
                $inserted++;
            } elseif ($identifier !== $balance->driver_code || (float) $balance->net_amount !== (float) $net || (float) $balance->tips_amount !== (float) $tips) {
                throw new RuntimeException("O saldo POP de {$name} diverge do já importado para este período.");
            } else {
                $duplicates++;
            }
        }

        if ($total === 0) {
            throw new RuntimeException('Ficheiro POP sem linhas de motoristas.');
        }

        return [
            'total' => $total,
            'inserted' => $inserted,
            'duplicates' => $duplicates,
            'invalid_rows' => $invalid,
            'skipped' => $invalid + $duplicates,
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
            'driver_codes' => array_values($names),
        ];
    }

    private function amount(string $value): ?string
    {
        $value = trim($value);
        if (! preg_match('/^-?(?:\d+|\d{1,3}(?:\.\d{3})+)(?:,\d{1,2})?$/D', $value)) {
            return null;
        }

        $amount = str_replace(['.', ','], ['', '.'], $value);

        return abs((float) $amount) < 100000000 ? $amount : null;
    }
}
