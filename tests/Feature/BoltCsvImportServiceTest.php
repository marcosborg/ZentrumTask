<?php

use App\Models\Driver;
use App\Models\PlatformDriverBalance;
use App\Services\BoltPlatformCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('imports bolt csv with explicit period', function () {
    $driver = Driver::factory()->create([
        'bolt_driver_code' => 'driver-123',
    ]);

    $csv = implode("\n", [
        'Identificador do motorista,Ganhos liquidos|EUR,Gorjetas dos passageiros|EUR',
        'driver-123,1000.50,80.20',
    ]);

    $path = storage_path('framework/testing/bolt-platform-'.Str::random(8).'.csv');
    file_put_contents($path, $csv);

    $result = app(BoltPlatformCsvImportService::class)->import($path, [
        'period_start' => '2026-02-02',
        'period_end' => '2026-02-08',
    ]);

    $balance = PlatformDriverBalance::query()->first();

    expect($result['inserted'])->toBe(1)
        ->and($result['skipped'])->toBe(0)
        ->and($result['period_start'])->toBe('2026-02-02')
        ->and($result['period_end'])->toBe('2026-02-08')
        ->and($result['driver_codes'])->toContain('driver-123')
        ->and($balance)->not->toBeNull()
        ->and($balance->platform)->toBe('bolt')
        ->and($balance->driver_code)->toBe('driver-123')
        ->and($balance->net_amount)->toBe('1000.50')
        ->and($balance->tips_amount)->toBe('80.20')
        ->and($balance->driver()->exists())->toBeFalse()
        ->and($driver->bolt_driver_code)->toBe('driver-123');
});

it('skips duplicate rows on reimport for same period and driver', function () {
    $csv = implode("\n", [
        'Identificador do motorista,Ganhos liquidos|EUR,Gorjetas dos passageiros|EUR',
        'driver-999,120.00,5.00',
    ]);

    $path = storage_path('framework/testing/bolt-platform-'.Str::random(8).'.csv');
    file_put_contents($path, $csv);

    $service = app(BoltPlatformCsvImportService::class);

    $service->import($path, [
        'period_start' => '2026-01-27',
        'period_end' => '2026-02-02',
    ]);

    $result = $service->import($path, [
        'period_start' => '2026-01-27',
        'period_end' => '2026-02-02',
    ]);

    expect($result['inserted'])->toBe(0)
        ->and($result['skipped'])->toBe(1)
        ->and($result['duplicates'])->toBe(1)
        ->and(PlatformDriverBalance::query()->count())->toBe(1);
});

it('marks rows without driver code as invalid', function () {
    $csv = implode("\n", [
        'Identificador do motorista,Ganhos liquidos|EUR,Gorjetas dos passageiros|EUR',
        ',90.00,7.00',
    ]);

    $path = storage_path('framework/testing/bolt-platform-'.Str::random(8).'.csv');
    file_put_contents($path, $csv);

    $result = app(BoltPlatformCsvImportService::class)->import($path, [
        'period_start' => '2026-01-27',
        'period_end' => '2026-02-02',
    ]);

    expect($result['inserted'])->toBe(0)
        ->and($result['invalid_rows'])->toBe(1)
        ->and($result['skipped'])->toBe(1)
        ->and(PlatformDriverBalance::query()->count())->toBe(0);
});
