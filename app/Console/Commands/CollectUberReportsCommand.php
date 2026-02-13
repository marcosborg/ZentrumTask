<?php

namespace App\Console\Commands;

use App\Services\PlatformConnectors\UberPlaywrightCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

class CollectUberReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:collect-uber-reports
        {--headed : Run browser in headed mode for debugging/login}
        {--max-downloads=1 : Maximum number of reports to download}
        {--timeout-seconds=180 : Timeout per collector run}
        {--manual-auth-seconds=300 : Time to wait for manual login/challenge resolution in headed mode}
        {--import : Trigger Uber import after collecting files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collect Uber reports automatically using Playwright';

    /**
     * Execute the console command.
     */
    public function handle(UberPlaywrightCollector $collector): int
    {
        $maxDownloads = (int) $this->option('max-downloads');
        $timeoutSeconds = (int) $this->option('timeout-seconds');
        $manualAuthSeconds = (int) $this->option('manual-auth-seconds');

        try {
            $result = $collector->collect(
                headed: (bool) $this->option('headed'),
                maxDownloads: max(1, $maxDownloads),
                timeoutSeconds: max(30, $timeoutSeconds),
                manualAuthSeconds: max(30, $manualAuthSeconds),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $downloaded = $result['downloaded'];
        $this->info("Relatorios Uber descarregados: {$downloaded}");

        if ($downloaded > 0) {
            foreach ($result['files'] as $file) {
                $this->line(' - '.$file);
            }
        }

        if (! $this->option('import')) {
            return self::SUCCESS;
        }

        $code = Artisan::call('platform:fetch-reports', [
            '--platform' => ['uber'],
        ]);
        $this->line(Artisan::output());

        return $code === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
