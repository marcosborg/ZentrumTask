<?php

use App\Support\DatabaseModeService;
use App\Support\DatabaseReplicationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    $this->modeDirectory = storage_path('framework/testing/mode-'.\Illuminate\Support\Str::uuid());
    File::makeDirectory($this->modeDirectory, 0700, true);
    file_put_contents($this->modeDirectory.'/.env', "DB_MODE=sandbox\n");
    $this->originalEnvironmentPath = app()->environmentPath();
    $this->originalBootstrapPath = app()->bootstrapPath();
    app()->useEnvironmentPath($this->modeDirectory);
    config(['database-management.directory' => $this->modeDirectory.'/management', 'database-management.local' => true,
        'database.mode' => 'sandbox', 'database.default' => 'mysql']);
});

afterEach(function (): void {
    app()->useEnvironmentPath($this->originalEnvironmentPath);
    app()->useBootstrapPath($this->originalBootstrapPath);
    File::deleteDirectory($this->modeDirectory);
});

it('validates the connection before persisting the new mode', function (): void {
    $this->mock(DatabaseReplicationService::class)->shouldReceive('probe')->with('production')->once()->andReturnNull();
    Artisan::shouldReceive('call')->with('config:clear', ['--no-interaction' => true])->once()->andReturn(0);
    Artisan::shouldReceive('call')->with('queue:restart', ['--no-interaction' => true])->once()->andReturn(0);
    app(DatabaseModeService::class)->switch('production');
    expect(file_get_contents($this->modeDirectory.'/.env'))->toBe("DB_MODE=production\n")
        ->and(config('database.mode'))->toBe('production')
        ->and(config('database.connections.mysql.url'))->toBeNull();
});

it('preserves the previous mode when the tunnel or database is unavailable', function (): void {
    $this->mock(DatabaseReplicationService::class)->shouldReceive('probe')->with('production')->once()
        ->andThrow(new RuntimeException('Tunel indisponivel.'));
    expect(fn () => app(DatabaseModeService::class)->switch('production'))->toThrow(RuntimeException::class);
    expect(file_get_contents($this->modeDirectory.'/.env'))->toBe("DB_MODE=sandbox\n")
        ->and(config('database.mode'))->toBe('sandbox');
});

it('rolls back the environment and connection when config cache rebuilding fails', function (): void {
    File::makeDirectory($this->modeDirectory.'/cache');
    app()->useBootstrapPath($this->modeDirectory);
    $cachedPath = app()->getCachedConfigPath();
    file_put_contents($cachedPath, '<?php return [];');
    app()->instance('config_loaded_from_cache', true);
    expect(app()->configurationIsCached())->toBeTrue();
    $this->mock(DatabaseReplicationService::class)->shouldReceive('probe')->with('production')->andReturnNull();
    $launcher = $this->mock(\App\Support\ManagedProcessLauncher::class);
    $launcher->shouldReceive('runArtisan')->with(['config:cache', '--no-interaction'], ['DB_MODE' => 'production'])->once()->andReturn(1);
    $launcher->shouldReceive('runArtisan')->with(['config:cache', '--no-interaction'], ['DB_MODE' => 'sandbox'])->once()->andReturn(0);

    expect(fn () => app(DatabaseModeService::class)->switch('production'))->toThrow(RuntimeException::class, 'Nao foi possivel atualizar a configuracao.');
    expect(file_get_contents($this->modeDirectory.'/.env'))->toBe("DB_MODE=sandbox\n")
        ->and(config('database.mode'))->toBe('sandbox');
});

it('never switches the deployed AWS application away from production', function (): void {
    config(['database-management.local' => false, 'database.mode' => 'production']);
    expect(fn () => app(DatabaseModeService::class)->switch('sandbox'))->toThrow(RuntimeException::class);
});
