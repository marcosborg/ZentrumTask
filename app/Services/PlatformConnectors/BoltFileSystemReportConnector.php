<?php

namespace App\Services\PlatformConnectors;

class BoltFileSystemReportConnector extends FileSystemPlatformReportConnector
{
    public function __construct(?string $inboxDirectory = null)
    {
        parent::__construct(
            connectorPlatform: 'bolt',
            inboxDirectory: $inboxDirectory ?? (string) config('services.platform_reports.bolt.directory', storage_path('app/platform-reports/bolt')),
        );
    }
}
