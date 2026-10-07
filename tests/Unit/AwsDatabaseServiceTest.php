<?php

use App\Support\AwsDatabaseService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

it('builds SSH tunnel commands with a loopback bind and strict forwarding checks', function (): void {
    config(['database-management.tunnel_port' => 13306, 'database.replication.production_dump.tunnel_target' => '127.0.0.1:3306']);
    $process = (new AwsDatabaseService)->tunnelProcess(['host' => '13.38.206.137', 'user' => 'ubuntu', 'key' => 'private-key.pem']);
    expect($process->getCommandLine())->toContain('127.0.0.1:13306:127.0.0.1:3306')->toContain('ExitOnForwardFailure=yes')
        ->toContain('BatchMode=yes')->toContain('ServerAliveInterval=15');
});

it('quotes remote POSIX scripts correctly even on Windows', function (): void {
    $process = (new AwsDatabaseService)->remote(['host' => '13.38.206.137', 'user' => 'ubuntu', 'key' => 'private-key.pem'], "echo 'safe'; exit 1");
    expect($process->getCommandLine())->toContain('bash -lc')->not->toContain('echo  safe');
});

it('rejects malformed remote container identifiers', function (): void {
    config(['database.replication.production_dump.container' => 'web; rm -rf /']);
    expect(fn () => (new AwsDatabaseService)->container())->toThrow(RuntimeException::class);
});

it('rejects a listening port that is not owned by a validated tunnel', function (): void {
    $directory = storage_path('framework/testing/tunnel-'.\Illuminate\Support\Str::uuid());
    config(['database-management.directory' => $directory, 'database-management.local' => true]);
    $service = new class extends AwsDatabaseService
    {
        public function portOpen(): bool
        {
            return true;
        }
    };

    try {
        expect(fn () => $service->ensureTunnel())->toThrow(RuntimeException::class, 'nao pertence');
    } finally {
        File::deleteDirectory($directory);
    }
});

it('keeps database credentials out of SSH scripts', function (): void {
    config(['database.profiles.production.password' => 'secret-database-password']);
    $service = new AwsDatabaseService;
    $process = $service->remote(['host' => '13.38.206.137', 'user' => 'ubuntu', 'key' => 'private-key.pem'], $service->dockerScript($service->credentialsScript().'mysql --execute="SELECT 1"'));
    expect($process->getCommandLine())->not->toContain('secret-database-password')->toContain('MYSQL_PWD');
});

it('reuses a managed live tunnel without starting another process', function (): void {
    $directory = storage_path('framework/testing/tunnel-'.\Illuminate\Support\Str::uuid());
    config(['database-management.directory' => $directory, 'database-management.local' => true, 'database-management.tunnel_port' => 13306]);
    $service = new class extends AwsDatabaseService
    {
        public bool $probed = false;

        public function portOpen(): bool
        {
            return true;
        }

        protected function probeTunnel(): void
        {
            $this->probed = true;
        }
    };
    $service->recordTunnelState(['port' => 13306, 'instance' => config('database.replication.production_dump.lightsail_instance'),
        'heartbeat' => time(), 'identity' => 'aws-db:1']);
    $this->mock(\App\Support\ManagedProcessLauncher::class)->shouldNotReceive('launch');

    try {
        $service->ensureTunnel();
        expect($service->probed)->toBeTrue();
    } finally {
        File::deleteDirectory($directory);
    }
});

it('starts a replacement tunnel when a previous connection is no longer listening', function (): void {
    $directory = storage_path('framework/testing/tunnel-'.\Illuminate\Support\Str::uuid());
    config(['database-management.directory' => $directory, 'database-management.local' => true, 'database-management.tunnel_port' => 13306]);
    $service = new class extends AwsDatabaseService
    {
        public bool $opened = false;

        public bool $probed = false;

        public function portOpen(): bool
        {
            return $this->opened;
        }

        protected function probeTunnel(): void
        {
            $this->probed = true;
        }
    };
    $this->mock(\App\Support\ManagedProcessLauncher::class)->shouldReceive('launch')->once()->andReturnUsing(function () use ($service): void {
        $service->recordTunnelState(['port' => 13306, 'instance' => config('database.replication.production_dump.lightsail_instance'),
            'heartbeat' => time(), 'identity' => 'aws-db:1']);
        $service->opened = true;
    });

    try {
        $service->ensureTunnel();
        expect($service->probed)->toBeTrue();
    } finally {
        File::deleteDirectory($directory);
    }
});
