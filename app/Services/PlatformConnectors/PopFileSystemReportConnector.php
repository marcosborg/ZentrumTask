<?php

namespace App\Services\PlatformConnectors;

class PopFileSystemReportConnector extends FileSystemPlatformReportConnector
{
    public function __construct(?string $inboxDirectory = null)
    {
        parent::__construct(
            connectorPlatform: 'pop',
            inboxDirectory: $inboxDirectory ?? (string) config('services.platform_reports.pop.directory', storage_path('app/platform-reports/pop')),
        );
    }
}
