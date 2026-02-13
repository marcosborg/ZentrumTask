<?php

namespace App\Services\PlatformConnectors;

use Illuminate\Support\Carbon;

interface PlatformReportConnector
{
    public function platform(): string;

    /**
     * @return array<int, PlatformReport>
     */
    public function fetchReports(?Carbon $periodStart = null, ?Carbon $periodEnd = null): array;
}
