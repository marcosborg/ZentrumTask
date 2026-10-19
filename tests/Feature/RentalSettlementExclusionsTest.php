<?php

use App\Filament\Pages\DriverSettlementsReport;
use App\Models\Driver;
use App\Models\DriverBalanceMovement;
use App\Models\DriverBillingProfile;
use App\Models\DriverSettlement;
use App\Models\PlatformDriverBalance;
use App\Models\VehicleAllocation;
use App\Services\DriverSettlementCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->driver = Driver::factory()->create();
    $this->driver->participations()->update(['starts_at' => '2026-01-01']);
    DriverBillingProfile::factory()->create([
        'driver_id' => $this->driver->id,
        'valid_from' => '2026-01-01',
    ]);
    VehicleAllocation::factory()->create([
        'driver_id' => $this->driver->id,
        'starts_at' => '2026-01-01',
        'ends_at' => null,
        'status' => 'active',
    ]);
});

it('preserves a period exclusion across regeneration and reimport', function (bool $individual) {
    $calculator = app(DriverSettlementCalculator::class);
    $calculator->calculate('2026-10-12', '2026-10-18', $this->driver->id);
    $settlement = DriverSettlement::query()->where('driver_id', $this->driver->id)->sole();
    $this->driver->forceFill(['rental_settlement_exclusions' => [
        ['period_start' => '2026-10-12', 'period_end' => '2026-10-18', 'reason' => 'Requested exclusion'],
    ]])->save();
    PlatformDriverBalance::query()->create([
        'driver_id' => $this->driver->id,
        'driver_code' => 'reimported-driver',
        'platform' => 'uber',
        'period_start' => '2026-10-12',
        'period_end' => '2026-10-18',
        'net_amount' => 100,
        'tips_amount' => 0,
        'source_file' => 'uber.csv',
        'imported_at' => now(),
    ]);

    $result = app(DriverSettlementsReport::class)->recalculateSettlement($settlement);
    expect($result['created'])->toBe(0)->and($result['skipped'])->toBe(1);
    $result = $calculator->calculate('2026-10-12', '2026-10-18', $individual ? $this->driver->id : null);
    expect($result['created'])->toBe(0)->and($result['skipped'])->toBe(1)
        ->and(DriverSettlement::query()->where('driver_id', $this->driver->id)->count())->toBe(0)
        ->and(DriverBalanceMovement::query()->where('driver_id', $this->driver->id)->where('type', 'settlement')->count())->toBe(0);
})->with([true, false]);

it('limits exclusions to the selected driver and overlapping dates', function (string $start, string $end, bool $excluded) {
    $this->driver->forceFill(['rental_settlement_exclusions' => [
        ['period_start' => '2026-10-12', 'period_end' => '2026-10-18'],
    ]])->save();
    $other = Driver::factory()->create();
    $other->participations()->update(['starts_at' => '2026-01-01']);
    DriverBillingProfile::factory()->create(['driver_id' => $other->id, 'valid_from' => '2026-01-01']);
    VehicleAllocation::factory()->create(['driver_id' => $other->id, 'starts_at' => '2026-01-01', 'ends_at' => null, 'status' => 'active']);

    app(DriverSettlementCalculator::class)->calculate($start, $end);

    expect(DriverSettlement::query()->where('driver_id', $this->driver->id)->exists())->toBe(! $excluded)
        ->and(DriverSettlement::query()->where('driver_id', $other->id)->exists())->toBeTrue();
})->with([
    ['2026-10-05', '2026-10-11', false],
    ['2026-10-19', '2026-10-25', false],
    ['2026-10-12', '2026-10-18', true],
    ['2026-10-01', '2026-10-31', true],
]);
