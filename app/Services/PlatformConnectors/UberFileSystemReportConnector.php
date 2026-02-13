<?php

namespace App\Services\PlatformConnectors;

class UberFileSystemReportConnector extends FileSystemPlatformReportConnector
{
    public function __construct(?string $inboxDirectory = null)
    {
        parent::__construct(
            connectorPlatform: 'uber',
            inboxDirectory: $inboxDirectory ?? (string) config('services.platform_reports.uber.directory', storage_path('app/platform-reports/uber')),
        );
    }
}
