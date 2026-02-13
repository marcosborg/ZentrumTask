<?php

use App\Services\PlatformConnectors\UberPlaywrightCollector;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

it('collects uber reports and shows downloaded files', function () {
    $collector = mock(UberPlaywrightCollector::class);
    $collector
        ->shouldReceive('collect')
        ->once()
        ->andReturn([
            'downloaded' => 2,
            'files' => [
                'storage/app/platform-reports/uber/report-1.csv',
                'storage/app/platform-reports/uber/report-2.csv',
            ],
            'output' => [],
        ]);

    app()->instance(UberPlaywrightCollector::class, $collector);

    $this->artisan('platform:collect-uber-reports')
        ->expectsOutputToContain('Relatorios Uber descarregados: 2')
        ->expectsOutputToContain('report-1.csv')
        ->expectsOutputToContain('report-2.csv')
        ->assertSuccessful();
});

it('fails when collector throws runtime exception', function () {
    $collector = mock(UberPlaywrightCollector::class);
    $collector
        ->shouldReceive('collect')
        ->once()
        ->andThrow(new \RuntimeException('Falha controlada de teste'));

    app()->instance(UberPlaywrightCollector::class, $collector);

    $this->artisan('platform:collect-uber-reports')
        ->expectsOutputToContain('Falha controlada de teste')
        ->assertFailed();
});
