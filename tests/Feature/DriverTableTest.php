<?php

use App\Filament\Resources\Drivers\Pages\ListDrivers;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('displays the billing profile kilometre limit for each driver', function (): void {
    $user = User::factory()->create();
    $driver = Driver::factory()->create();
    DriverBillingProfile::factory()->create([
        'driver_id' => $driver->id,
        'extra_km_limit' => 2500,
    ]);

    Livewire::actingAs($user)
        ->test(ListDrivers::class)
        ->assertCanSeeTableRecords([$driver])
        ->assertTableColumnStateSet('billingProfile.extra_km_limit', '2500.00', $driver);
});
