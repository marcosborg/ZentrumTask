<?php

namespace App\Console\Commands;

use App\Jobs\FetchPlatformReportsJob;
use App\Services\PlatformConnectors\FetchPlatformReportsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use RuntimeException;

class FetchPlatformReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:fetch-reports
        {--platform=* : Platform(s): bolt, uber}
        {--period-start= : Optional period start (Y-m-d)}
        {--period-end= : Optional period end (Y-m-d)}
        {--queue : Dispatch job to queue instead of running inline}
        {--queue-name= : Optional queue name when using --queue}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch and import Bolt/Uber platform CSV reports using configured connectors';

    /**
     * Execute the console command.
     */
    public function handle(
        FetchPlatformReportsService $service,
    ): int {
        try {
            [$periodStart, $periodEnd] = $this->resolvePeriod();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        try {
            $platforms = $this->resolvePlatforms();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $queueMode = (bool) $this->option('queue');

        if ($queueMode) {
            $job = new FetchPlatformReportsJob(
                platforms: $platforms === [] ? null : $platforms,
                periodStart: $periodStart?->toDateString(),
                periodEnd: $periodEnd?->toDateString(),
            );

            $queueName = $this->resolveQueueName();
            if ($queueName !== null) {
                dispatch($job->onQueue($queueName));
            } else {
                dispatch($job);
            }

            $this->info('FetchPlatformReportsJob dispatched successfully.');

            return self::SUCCESS;
        }

        try {
            $summary = $service->fetchAndImport(
                platforms: $platforms === [] ? null : $platforms,
                periodStart: $periodStart,
                periodEnd: $periodEnd,
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($summary['platforms'] as $platformSummary) {
            $platform = strtoupper($platformSummary['platform']);
            $found = (int) $platformSummary['reports_found'];
            $imported = (int) $platformSummary['imported'];
            $failed = (int) $platformSummary['failed'];

            $this->line("{$platform}: encontrados={$found}, importados={$imported}, falhas={$failed}");
        }

        $totals = $summary['totals'];
        $this->info(
            "Totais -> plataformas={$totals['platforms']}, reports={$totals['reports']}, importados={$totals['imported']}, falhas={$totals['failed']}"
        );

        return self::SUCCESS;
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    protected function resolvePeriod(): array
    {
        $periodStartRaw = $this->option('period-start');
        $periodEndRaw = $this->option('period-end');

        if (($periodStartRaw && ! $periodEndRaw) || ($periodEndRaw && ! $periodStartRaw)) {
            throw new RuntimeException('Informe ambos --period-start e --period-end, ou nenhum.');
        }

        if (! $periodStartRaw || ! $periodEndRaw) {
            return [null, null];
        }

        try {
            return [
                Carbon::parse((string) $periodStartRaw)->startOfDay(),
                Carbon::parse((string) $periodEndRaw)->endOfDay(),
            ];
        } catch (\Throwable) {
            throw new RuntimeException('Datas invalidas. Use o formato Y-m-d em --period-start e --period-end.');
        }
    }

    /**
     * @return array<int, string>
     */
    protected function resolvePlatforms(): array
    {
        $requested = collect((array) $this->option('platform'))
            ->map(fn (string $platform): string => trim($platform))
            ->filter(fn (string $platform): bool => $platform !== '')
            ->values();

        $normalized = $requested
            ->map(fn (string $platform): string => strtolower(trim($platform)))
            ->filter(fn (string $platform): bool => in_array($platform, ['bolt', 'uber'], true))
            ->unique()
            ->values()
            ->all();

        if ($requested->isNotEmpty() && $normalized === []) {
            throw new RuntimeException('Plataformas invalidas. Use --platform=bolt e/ou --platform=uber.');
        }

        return $normalized;
    }

    protected function resolveQueueName(): ?string
    {
        $queueName = trim((string) $this->option('queue-name'));

        return $queueName === '' ? null : $queueName;
    }
}
