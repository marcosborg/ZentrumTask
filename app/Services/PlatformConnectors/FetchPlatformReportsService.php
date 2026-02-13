<?php

namespace App\Services\PlatformConnectors;

use App\Services\BoltPlatformCsvImportService;
use App\Services\UberPlatformCsvImportService;
use Illuminate\Support\Carbon;
use RuntimeException;

class FetchPlatformReportsService
{
    public function __construct(
        protected PlatformReportConnectorResolver $connectorResolver,
        protected BoltPlatformCsvImportService $boltImportService,
        protected UberPlatformCsvImportService $uberImportService,
    ) {}

    /**
     * @param  array<int, string>|null  $platforms
     * @return array{
     *     totals: array{platforms:int,reports:int,imported:int,failed:int},
     *     platforms: array<int, array{
     *         platform:string,
     *         reports_found:int,
     *         imported:int,
     *         failed:int,
     *         files: array<int, array<string, mixed>>
     *     }>
     * }
     */
    public function fetchAndImport(
        ?array $platforms = null,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null
    ): array {
        $targetPlatforms = $this->normalizePlatforms($platforms);
        $summary = [
            'totals' => [
                'platforms' => count($targetPlatforms),
                'reports' => 0,
                'imported' => 0,
                'failed' => 0,
            ],
            'platforms' => [],
        ];

        foreach ($targetPlatforms as $platform) {
            $connector = $this->connectorResolver->resolve($platform);
            $reports = $connector->fetchReports($periodStart, $periodEnd);

            $platformSummary = [
                'platform' => $platform,
                'reports_found' => count($reports),
                'imported' => 0,
                'failed' => 0,
                'files' => [],
            ];

            foreach ($reports as $report) {
                $summary['totals']['reports']++;

                $importOptions = $this->resolveImportOptions($report, $periodStart, $periodEnd);

                try {
                    $result = $this->importReport($report, $importOptions);

                    $platformSummary['imported']++;
                    $summary['totals']['imported']++;
                    $platformSummary['files'][] = [
                        'filename' => $report->filename,
                        'status' => 'imported',
                        'result' => $result,
                    ];
                } catch (RuntimeException $exception) {
                    $platformSummary['failed']++;
                    $summary['totals']['failed']++;
                    $platformSummary['files'][] = [
                        'filename' => $report->filename,
                        'status' => 'failed',
                        'error' => $exception->getMessage(),
                    ];
                }
            }

            $summary['platforms'][] = $platformSummary;
        }

        return $summary;
    }

    /**
     * @param  array<int, string>|null  $platforms
     * @return array<int, string>
     */
    protected function normalizePlatforms(?array $platforms): array
    {
        $supported = ['bolt', 'uber'];

        if ($platforms === null || $platforms === []) {
            return $supported;
        }

        $normalized = collect($platforms)
            ->map(fn (string $platform): string => strtolower(trim($platform)))
            ->filter(fn (string $platform): bool => in_array($platform, $supported, true))
            ->unique()
            ->values()
            ->all();

        if ($normalized === []) {
            throw new RuntimeException('Nenhuma plataforma valida fornecida. Use bolt e/ou uber.');
        }

        return $normalized;
    }

    /**
     * @return array{period_start?: string, period_end?: string}
     */
    protected function resolveImportOptions(
        PlatformReport $report,
        ?Carbon $requestedPeriodStart,
        ?Carbon $requestedPeriodEnd
    ): array {
        $start = $report->periodStart ?? $requestedPeriodStart;
        $end = $report->periodEnd ?? $requestedPeriodEnd;

        if (! $start || ! $end) {
            return [];
        }

        return [
            'period_start' => $start->toDateString(),
            'period_end' => $end->toDateString(),
        ];
    }

    /**
     * @param  array{period_start?: string, period_end?: string}  $options
     * @return array<string, mixed>
     */
    protected function importReport(PlatformReport $report, array $options): array
    {
        return match ($report->platform) {
            'bolt' => $this->boltImportService->import($report->path, $options),
            'uber' => $this->uberImportService->import($report->path, $options),
            default => throw new RuntimeException('Plataforma nao suportada para import: '.$report->platform),
        };
    }
}
