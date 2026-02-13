<?php

use App\Jobs\FetchPlatformReportsJob;
use App\Models\PlatformDriverBalance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    $base = storage_path('framework/testing/platform-reports-'.uniqid());
    $this->boltDirectory = $base.DIRECTORY_SEPARATOR.'bolt';
    $this->uberDirectory = $base.DIRECTORY_SEPARATOR.'uber';

    mkdir($this->boltDirectory, 0777, true);
    mkdir($this->uberDirectory, 0777, true);

    config()->set('services.platform_reports.bolt.directory', $this->boltDirectory);
    config()->set('services.platform_reports.uber.directory', $this->uberDirectory);
});

afterEach(function () {
    $directories = [$this->boltDirectory, $this->uberDirectory];

    foreach ($directories as $directory) {
        if (! is_dir($directory)) {
            continue;
        }

        $files = glob($directory.DIRECTORY_SEPARATOR.'*') ?: [];
        foreach ($files as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($directory);
    }

    $base = dirname($this->boltDirectory);
    if (is_dir($base)) {
        rmdir($base);
    }
});

it('fetches and imports bolt and uber reports inline', function () {
    file_put_contents(
        $this->boltDirectory.DIRECTORY_SEPARATOR.'bolt-2026W06.csv',
        implode("\n", [
            'Identificador do motorista,Ganhos liquidos|EUR,Gorjetas dos passageiros|EUR',
            'bolt-driver-1,100.50,10.00',
        ])
    );

    file_put_contents(
        $this->uberDirectory.DIRECTORY_SEPARATOR.'uber-2026W06.csv',
        implode("\n", [
            'UUID do motorista,Pago a si,Pago a si:Os seus rendimentos:Gratificação',
            'uber-driver-1,220.00,30.00',
        ])
    );

    $this->artisan('platform:fetch-reports', [
        '--period-start' => '2026-02-02',
        '--period-end' => '2026-02-08',
    ])
        ->expectsOutputToContain('BOLT: encontrados=1, importados=1, falhas=0')
        ->expectsOutputToContain('UBER: encontrados=1, importados=1, falhas=0')
        ->expectsOutputToContain('Totais -> plataformas=2, reports=2, importados=2, falhas=0')
        ->assertSuccessful();

    expect(PlatformDriverBalance::query()->count())->toBe(2)
        ->and(PlatformDriverBalance::query()->where('platform', 'bolt')->exists())->toBeTrue()
        ->and(PlatformDriverBalance::query()->where('platform', 'uber')->exists())->toBeTrue();
});

it('dispatches fetch job when queue option is used', function () {
    Bus::fake();

    $this->artisan('platform:fetch-reports', [
        '--queue' => true,
        '--platform' => ['bolt'],
        '--period-start' => '2026-02-02',
        '--period-end' => '2026-02-08',
    ])->assertSuccessful();

    Bus::assertDispatched(FetchPlatformReportsJob::class, function (FetchPlatformReportsJob $job): bool {
        return $job->platforms === ['bolt']
            && $job->periodStart === '2026-02-02'
            && $job->periodEnd === '2026-02-08';
    });
});

it('fails when only invalid platforms are provided', function () {
    $this->artisan('platform:fetch-reports', [
        '--platform' => ['foo'],
    ])
        ->expectsOutputToContain('Plataformas invalidas')
        ->assertFailed();
});
