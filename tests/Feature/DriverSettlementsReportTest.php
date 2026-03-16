<?php

use App\Filament\Pages\DriverSettlementsReport;
use App\Models\Driver;
use App\Models\DriverAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('deletes a manual adjustment directly from the settlements report modal', function () {
    $driver = Driver::factory()->create();

    $adjustment = DriverAdjustment::query()->create([
        'driver_id' => $driver->id,
        'starts_at' => '2026-03-02',
        'recurrence_weeks' => null,
        'category' => 'acerto',
        'description' => 'Ajuste para eliminar',
        'amount' => 42.50,
        'external_ref' => 'manual-test-delete',
        'raw_row' => ['origin' => 'test'],
        'source_file' => 'manual',
        'imported_at' => now(),
    ]);

    $page = new DriverSettlementsReport;
    $page->deleteManualAdjustment($driver->id, $adjustment->id);

    expect(DriverAdjustment::query()->find($adjustment->id))->toBeNull();
});
