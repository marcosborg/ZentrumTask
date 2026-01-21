<?php

namespace App\Console\Commands;

use App\Services\DriverSettlementCalculator;
use Illuminate\Console\Command;
use RuntimeException;

class SettleDrivers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:settle-drivers {period_start} {period_end}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create driver settlements from platform balances for a period';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $periodStart = (string) $this->argument('period_start');
        $periodEnd = (string) $this->argument('period_end');

        try {
            $result = app(DriverSettlementCalculator::class)
                ->calculate($periodStart, $periodEnd);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Settlements criados: {$result['created']}");
        $this->info("Skips (existentes): {$result['skipped']}");
        $this->info("Drivers sem perfil ativo: {$result['missing_profiles']}");

        return self::SUCCESS;
    }
}
