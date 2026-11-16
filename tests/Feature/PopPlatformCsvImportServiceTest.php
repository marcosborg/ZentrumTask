<?php

use App\Filament\Pages\DriverSettlementsReport;
use App\Filament\Pages\PlatformImports;
use App\Models\Driver;
use App\Models\DriverBillingProfile;
use App\Models\DriverSettlement;
use App\Models\PlatformDriverBalance;
use App\Models\User;
use App\Services\DriverSettlementCalculator;
use App\Services\PlatformDriverBalanceAllocator;
use App\Services\PopPlatformCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->path = tempnam(sys_get_temp_dir(), 'pop');
    $this->period = ['period_start' => '2026-11-09', 'period_end' => '2026-11-15'];
    file_put_contents($this->path, "\xEF\xBB\xBF".implode("\n", [
        'Motorista;"Total Ganhos";"Valor das viagens";Gorjetas;"Compensações de cancelamento";Portagens;Devoluções;"Ganhos Brutos";"Taxa Recebimento";"Ganhos Líquidos";"Ganhos de referência";Viagens;"Distância (km)"',
        '"Pedro Pinto";3,50;3,50;0,00;0,00;0,00;0,00;3,50;0,11;3,40;0,00;1;2,88',
        '"Érica Silva";100,00;90,00;10,00;0,00;0,00;0,00;100,00;3,00;97,00;0,00;4;60,00',
    ]));
});

afterEach(function (): void {
    unlink($this->path);
});

it('allocates POP by registered name and pays the reported net and tips once', function (): void {
    Driver::factory()->create(['name' => 'Pedro Pinto']);
    $driver = Driver::factory()->create(['name' => 'Érica da Silva', 'pop_driver_name' => 'ÉRICA SILVA']);
    DriverBillingProfile::factory()->create([
        'driver_id' => $driver->id, 'valid_from' => '2026-01-01', 'percent_driver' => 60, 'percent_company' => 40,
    ]);

    $service = app(PopPlatformCsvImportService::class);
    expect($service->import($this->path, $this->period)['inserted'])->toBe(2)
        ->and($service->import($this->path, $this->period)['duplicates'])->toBe(2)
        ->and(app(PlatformDriverBalanceAllocator::class)->allocate('pop'))->toBe(['allocated' => 1, 'pending' => 1]);

    $pending = PlatformDriverBalance::query()->whereNull('driver_id')->sole();
    expect($pending->raw_row['Motorista'])->toBe('Pedro Pinto')
        ->and($pending->net_amount)->toBe('3.40');

    app(DriverSettlementCalculator::class)->calculate(...array_values($this->period));
    $settlement = DriverSettlement::query()->sole();
    expect($settlement->driver_id)->toBe($driver->id)
        ->and($settlement->net_total)->toBe('97.00')
        ->and($settlement->tips_total)->toBe('10.00')
        ->and($settlement->amount_payable)->toBe('62.20');

    $this->actingAs(User::factory()->create());
    Livewire::test(DriverSettlementsReport::class, ['filtersForm' => ['platform' => 'pop'] + $this->period])
        ->assertTableColumnFormattedStateSet('pop_net', '97,00 €', $settlement->id)
        ->assertTableColumnStateSet('total_operadores', 97, $settlement->id);

    $pedro = Driver::query()->where('name', 'Pedro Pinto')->sole();
    $pedro->update(['pop_driver_name' => 'Pedro Pinto']);
    app(PlatformDriverBalanceAllocator::class)->allocate('pop');
    expect($pending->refresh()->driver_id)->toBe($pedro->id);
});

it('requires a valid period for undated POP balances', function (array $period): void {
    expect(fn () => app(PopPlatformCsvImportService::class)->import($this->path, $period))->toThrow(RuntimeException::class, 'período válido');
})->with([[[]], [['period_start' => '2026-11-15', 'period_end' => '2026-11-09']]]);

it('rejects malformed POP data without creating a zero balance', function (): void {
    file_put_contents($this->path, "Motorista;Ganhos Líquidos;Gorjetas\nPedro;bad;0,00\n;3,40;0,00\nPedro;3,40");
    expect(app(PopPlatformCsvImportService::class)->import($this->path, $this->period)['invalid_rows'])->toBe(3)
        ->and(PlatformDriverBalance::query()->count())->toBe(0);

    file_put_contents($this->path, "Motorista;Ganhos Brutos;Gorjetas\nPedro;3,50;0,00");
    expect(fn () => app(PopPlatformCsvImportService::class)->import($this->path, $this->period))->toThrow(RuntimeException::class, 'Cabeçalho POP inválido');
});

it('rolls back an ambiguous POP report and preserves balances on a conflicting reimport', function (): void {
    $header = "Motorista;Ganhos Líquidos;Gorjetas\n";
    $service = app(PopPlatformCsvImportService::class);
    file_put_contents($this->path, $header."Pedro;3,40;0,00\nPEDRO;9,00;0,00");
    expect(fn () => $service->import($this->path, $this->period))->toThrow(RuntimeException::class, 'Nome POP repetido')
        ->and(PlatformDriverBalance::query()->count())->toBe(0);

    file_put_contents($this->path, $header.'Pedro;3,40;0,00');
    $service->import($this->path, $this->period);
    file_put_contents($this->path, $header."Ana;8,00;0,00\nPedro;9,00;0,00");
    expect(fn () => $service->import($this->path, $this->period))->toThrow(RuntimeException::class, 'diverge')
        ->and(PlatformDriverBalance::query()->sole()->net_amount)->toBe('3.40');
});

it('imports POP from the platform form and lists pending names as registered', function (): void {
    $this->actingAs(User::factory()->create());
    Storage::fake('local')->put('platform-imports/pop.csv', file_get_contents($this->path));

    Livewire::test(PlatformImports::class)
        ->fillForm(['platform' => 'pop', 'file' => ['platform-imports/pop.csv']])
        ->call('runImport')
        ->assertHasFormErrors(['period_start' => 'required', 'period_end' => 'required'])
        ->fillForm(['platform' => 'pop', 'file' => ['platform-imports/pop.csv']] + $this->period)
        ->call('runImport')
        ->assertHasNoFormErrors()
        ->assertSet('result.inserted', 2)
        ->assertSee('Pedro Pinto')
        ->assertSee('Érica Silva')
        ->assertSee('Nome POP');
});
