<?php

namespace App\Console\Commands;

use App\Models\TeslaAccount;
use App\Services\TeslaService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

class TeslaSyncVehicles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tesla:sync-vehicles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Tesla vehicles for all connected Tesla accounts';

    public function __construct(private readonly TeslaService $teslaService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $accounts = TeslaAccount::query()->get();

        if ($accounts->isEmpty()) {
            $this->warn('No Tesla accounts found.');

            return self::SUCCESS;
        }

        $totalSynced = 0;

        foreach ($accounts as $account) {
            try {
                $synced = $this->teslaService->syncVehicles($account);
                $totalSynced += $synced;

                $this->info("Tesla account {$account->getKey()}: {$synced} vehicle(s) synced.");
                Log::info('Tesla vehicles synced.', [
                    'tesla_account_id' => $account->getKey(),
                    'vehicles' => $synced,
                ]);
            } catch (RequestException $exception) {
                $this->error("Tesla account {$account->getKey()}: sync failed.");
                Log::warning('Tesla vehicles sync failed.', [
                    'tesla_account_id' => $account->getKey(),
                    'status' => $exception->response?->status(),
                    'body' => $exception->response?->json(),
                ]);
            }
        }

        $this->info("Done. {$totalSynced} vehicle(s) synced.");

        return self::SUCCESS;
    }
}
