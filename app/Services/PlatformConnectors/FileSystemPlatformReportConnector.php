<?php

namespace App\Services\PlatformConnectors;

use Illuminate\Support\Carbon;
use RuntimeException;

abstract class FileSystemPlatformReportConnector implements PlatformReportConnector
{
    public function __construct(
        protected string $connectorPlatform,
        protected string $inboxDirectory,
    ) {
        $this->inboxDirectory = $this->resolveInboxDirectory($this->inboxDirectory);
    }

    public function platform(): string
    {
        return $this->connectorPlatform;
    }

    /**
     * @return array<int, PlatformReport>
     */
    public function fetchReports(?Carbon $periodStart = null, ?Carbon $periodEnd = null): array
    {
        if (! is_dir($this->inboxDirectory)) {
            return [];
        }

        $files = glob($this->inboxDirectory.DIRECTORY_SEPARATOR.'*.csv') ?: [];
        $reports = [];

        foreach ($files as $path) {
            if (! is_file($path)) {
                continue;
            }

            $filename = basename($path);
            [$detectedStart, $detectedEnd] = $this->detectPeriodFromFilename($filename);

            if (! $this->isWithinPeriod($periodStart, $periodEnd, $detectedStart, $detectedEnd)) {
                continue;
            }

            $checksum = hash_file('sha256', $path);

            if (! is_string($checksum)) {
                throw new RuntimeException('Falha ao calcular checksum para '.$path);
            }

            $reports[] = new PlatformReport(
                platform: $this->platform(),
                path: $path,
                filename: $filename,
                checksum: $checksum,
                periodStart: $detectedStart,
                periodEnd: $detectedEnd,
            );
        }

        usort($reports, function (PlatformReport $left, PlatformReport $right): int {
            return strcmp($left->filename, $right->filename);
        });

        return $reports;
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    protected function detectPeriodFromFilename(string $filename): array
    {
        if (preg_match('/\b(\d{8})-(\d{8})\b/', $filename, $matches) === 1) {
            $start = Carbon::createFromFormat('Ymd', $matches[1])->startOfDay();
            $end = Carbon::createFromFormat('Ymd', $matches[2])->endOfDay();

            return [$start, $end];
        }

        if (preg_match('/\b(20\d{2})W(\d{2})\b/i', $filename, $matches) === 1) {
            $year = (int) $matches[1];
            $week = (int) $matches[2];

            $start = Carbon::now()->setISODate($year, $week, 1)->startOfDay();
            $end = Carbon::now()->setISODate($year, $week, 7)->endOfDay();

            return [$start, $end];
        }

        return [null, null];
    }

    protected function isWithinPeriod(
        ?Carbon $requestedStart,
        ?Carbon $requestedEnd,
        ?Carbon $detectedStart,
        ?Carbon $detectedEnd,
    ): bool {
        if (! $requestedStart || ! $requestedEnd) {
            return true;
        }

        if (! $detectedStart || ! $detectedEnd) {
            return true;
        }

        return $detectedEnd->gte($requestedStart->startOfDay())
            && $detectedStart->lte($requestedEnd->endOfDay());
    }

    protected function resolveInboxDirectory(string $path): string
    {
        $normalized = trim($path);

        if ($normalized === '') {
            return base_path('storage/app/platform-reports/'.$this->connectorPlatform);
        }

        if ($this->isAbsolutePath($normalized)) {
            return $normalized;
        }

        return base_path($normalized);
    }

    protected function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
