<?php

namespace App\Services\PlatformConnectors;

use Illuminate\Support\Carbon;

final class PlatformReport
{
    public function __construct(
        public string $platform,
        public string $path,
        public string $filename,
        public string $checksum,
        public ?Carbon $periodStart = null,
        public ?Carbon $periodEnd = null,
    ) {}
}
