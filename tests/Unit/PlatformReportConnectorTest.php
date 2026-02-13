<?php

use App\Services\PlatformConnectors\BoltFileSystemReportConnector;
use App\Services\PlatformConnectors\PlatformReportConnectorResolver;
use App\Services\PlatformConnectors\UberFileSystemReportConnector;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->reportsDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'zentrum-platform-connectors-'.uniqid();
    mkdir($this->reportsDir, 0777, true);
});

afterEach(function () {
    if (! is_dir($this->reportsDir)) {
        return;
    }

    $files = glob($this->reportsDir.DIRECTORY_SEPARATOR.'*') ?: [];

    foreach ($files as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }

    rmdir($this->reportsDir);
});

it('fetches csv reports from filesystem connector', function () {
    file_put_contents($this->reportsDir.DIRECTORY_SEPARATOR.'bolt-20260202-20260208.csv', 'a,b,c');
    file_put_contents($this->reportsDir.DIRECTORY_SEPARATOR.'bolt-2026W07.csv', 'a,b,c');
    file_put_contents($this->reportsDir.DIRECTORY_SEPARATOR.'ignore.txt', 'noop');

    $connector = new BoltFileSystemReportConnector($this->reportsDir);
    $reports = $connector->fetchReports();

    expect($reports)->toHaveCount(2)
        ->and($reports[0]->platform)->toBe('bolt')
        ->and($reports[0]->filename)->toBe('bolt-20260202-20260208.csv')
        ->and($reports[0]->checksum)->not->toBe('')
        ->and($reports[1]->filename)->toBe('bolt-2026W07.csv');
});

it('filters by requested period when filename period is available', function () {
    file_put_contents($this->reportsDir.DIRECTORY_SEPARATOR.'bolt-20260202-20260208.csv', 'a,b,c');
    file_put_contents($this->reportsDir.DIRECTORY_SEPARATOR.'bolt-20260302-20260308.csv', 'a,b,c');
    file_put_contents($this->reportsDir.DIRECTORY_SEPARATOR.'bolt-without-period.csv', 'a,b,c');

    $connector = new BoltFileSystemReportConnector($this->reportsDir);
    $reports = $connector->fetchReports(
        Carbon::parse('2026-02-01'),
        Carbon::parse('2026-02-10'),
    );

    $filenames = array_map(fn ($report): string => $report->filename, $reports);

    expect($filenames)->toContain('bolt-20260202-20260208.csv')
        ->and($filenames)->toContain('bolt-without-period.csv')
        ->and($filenames)->not->toContain('bolt-20260302-20260308.csv');
});

it('resolves connector by platform', function () {
    $boltConnector = new BoltFileSystemReportConnector($this->reportsDir);
    $uberConnector = new UberFileSystemReportConnector($this->reportsDir);

    $resolver = new PlatformReportConnectorResolver(
        boltConnector: $boltConnector,
        uberConnector: $uberConnector,
    );

    expect($resolver->resolve('bolt'))->toBe($boltConnector)
        ->and($resolver->resolve('uber'))->toBe($uberConnector);
});
