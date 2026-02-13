<?php

use App\Models\PlatformDriverBalance;
use App\Services\UberPlatformCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('normalizes monday to next monday uber periods into monday to sunday', function () {
    $csv = implode("\n", [
        'UUID do motorista,Pago a si,Pago a si:Os seus rendimentos:Gratificação',
        'uber-driver-123,250.00,30.00',
    ]);

    $path = storage_path('framework/testing/uber-platform-'.Str::random(8).'.csv');
    file_put_contents($path, $csv);

    $result = app(UberPlatformCsvImportService::class)->import($path, [
        'period_start' => '2026-02-02',
        'period_end' => '2026-02-09',
    ]);

    $balance = PlatformDriverBalance::query()->firstOrFail();

    expect($result['period_start'])->toBe('2026-02-02')
        ->and($result['period_end'])->toBe('2026-02-08')
        ->and($balance->period_start?->toDateString())->toBe('2026-02-02')
        ->and($balance->period_end?->toDateString())->toBe('2026-02-08');
});
