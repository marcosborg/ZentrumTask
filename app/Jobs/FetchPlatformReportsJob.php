<?php

namespace App\Jobs;

use App\Services\PlatformConnectors\FetchPlatformReportsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class FetchPlatformReportsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, string>|null  $platforms
     */
    public function __construct(
        public ?array $platforms = null,
        public ?string $periodStart = null,
        public ?string $periodEnd = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(FetchPlatformReportsService $service): void
    {
        $periodStart = $this->periodStart ? Carbon::parse($this->periodStart) : null;
        $periodEnd = $this->periodEnd ? Carbon::parse($this->periodEnd) : null;

        $summary = $service->fetchAndImport(
            platforms: $this->platforms,
            periodStart: $periodStart,
            periodEnd: $periodEnd,
        );

        Log::info('Platform reports fetch job finished', [
            'platforms' => $this->platforms,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'totals' => $summary['totals'],
        ]);
    }
}
