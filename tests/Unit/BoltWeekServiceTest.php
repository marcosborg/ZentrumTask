<?php

use App\Services\BoltPlatformCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('infers monday to sunday period from iso week in filename', function () {
    $csv = implode("\n", [
        'Identificador do motorista,Ganhos liquidos|EUR,Gorjetas dos passageiros|EUR',
        'driver-wk,50.00,2.00',
    ]);

    $path = storage_path('framework/testing/bolt-platform-2026W06-'.Str::random(8).'.csv');
    file_put_contents($path, $csv);

    $result = app(BoltPlatformCsvImportService::class)->import($path);

    expect($result['period_start'])->toBe('2026-02-02')
        ->and($result['period_end'])->toBe('2026-02-08');
});
