<?php

use App\Models\BoltDriverEarning;
use App\Models\BoltSyncRun;
use App\Models\Driver;
use App\Services\BoltCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('imports a csv and associates driver by bolt uuid', function () {
    $driver = Driver::factory()->create([
        'bolt_driver_uuid' => 'uuid-bolt-123',
        'email' => 'bolt@example.com',
        'name' => 'Bolt Driver',
    ]);

    $csv = implode("\n", [
        'Identificador do motorista,Motorista,Email,Data,Ganhos brutos (total),Ganhos líquidos,Pagamento previsto',
        'uuid-bolt-123,Bolt Driver,bolt@example.com,29/12/2025,100,80,75',
    ]);

    $path = storage_path('framework/testing/bolt-'.Str::random(8).'.csv');
    file_put_contents($path, $csv);

    $service = app(BoltCsvImportService::class);
    $syncRun = $service->import($path);

    expect($syncRun)->toBeInstanceOf(BoltSyncRun::class)
        ->and($syncRun->status)->toBe('completed');

    $earning = BoltDriverEarning::query()->first();

    expect($earning)->not->toBeNull()
        ->and($earning->driver_id)->toBe($driver->id)
        ->and($earning->week_start->format('Y-m-d'))->toBe('2025-12-29')
        ->and($earning->week_end->format('Y-m-d'))->toBe('2026-01-04')
        ->and($earning->gross_total)->toBe('100.00')
        ->and($earning->net_total)->toBe('80.00')
        ->and($earning->expected_payment)->toBe('75.00');
});

it('reimports the same csv without duplicating', function () {
    $driver = Driver::factory()->create([
        'bolt_driver_uuid' => 'uuid-bolt-999',
        'email' => 'bolt999@example.com',
        'name' => 'Bolt Nine',
    ]);

    $csv = implode("\n", [
        'Identificador do motorista,Motorista,Email,Data,Ganhos brutos (total),Ganhos líquidos',
        'uuid-bolt-999,Bolt Nine,bolt999@example.com,2025-12-30,120,95',
    ]);

    $path = storage_path('framework/testing/bolt-'.Str::random(8).'.csv');
    file_put_contents($path, $csv);

    $service = app(BoltCsvImportService::class);
    $service->import($path);
    $service->import($path);

    expect(BoltDriverEarning::query()->count())->toBe(1)
        ->and(BoltDriverEarning::query()->first()->driver_id)->toBe($driver->id);
});

it('marks earnings as unresolved when driver is not found', function () {
    $csv = implode("\n", [
        'Identificador do motorista,Motorista,Email,Data,Ganhos brutos (total),Ganhos líquidos',
        'uuid-missing,Sem Motorista,missing@example.com,2025-12-31,90,70',
    ]);

    $path = storage_path('framework/testing/bolt-'.Str::random(8).'.csv');
    file_put_contents($path, $csv);

    $service = app(BoltCsvImportService::class);
    $service->import($path);

    $earning = BoltDriverEarning::query()->first();

    expect($earning)->not->toBeNull()
        ->and($earning->driver_id)->toBeNull()
        ->and($earning->driver_resolved)->toBeFalse();
});
