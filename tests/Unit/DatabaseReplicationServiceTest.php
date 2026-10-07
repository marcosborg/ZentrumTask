<?php

use App\Support\DatabaseReplicationService;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

function invokeReplicationMethod(DatabaseReplicationService $service, string $method, mixed ...$arguments): mixed
{
    $reflection = new ReflectionMethod($service, $method);

    return $reflection->invoke($service, ...$arguments);
}

it('keeps database passwords out of local process command lines', function () {
    $profile = [
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'zentrumtask',
        'username' => 'zentrum',
        'password' => 'super-secret-password',
    ];

    /** @var Process $process */
    $process = invokeReplicationMethod(
        new DatabaseReplicationService,
        'buildDumpProcess',
        $profile,
        'mysqldump',
        true
    );

    expect($process->getCommandLine())
        ->not->toContain('super-secret-password')
        ->not->toContain('--password=');
});

it('removes the MariaDB sandbox header before importing into older clients', function () {
    $dump = "/*M!999999\\- enable the sandbox mode */\nCREATE TABLE users (id INT);\n";

    $sanitized = invokeReplicationMethod(
        new DatabaseReplicationService,
        'sanitizeDumpContents',
        $dump
    );

    expect($sanitized)->toBe("CREATE TABLE users (id INT);\n");
});

it('passes aws profile directories to child processes', function () {
    $environment = invokeReplicationMethod(
        new DatabaseReplicationService,
        'processEnvironment',
        'database-password'
    );

    expect($environment)
        ->toHaveKey('MYSQL_PWD', 'database-password')
        ->toHaveKey('HOME')
        ->toHaveKey('USERPROFILE');
});

function replicationTestService(bool $backupFails = false, bool $importFails = false, bool $exists = true): \App\Support\DatabaseReplicationService
{
    $aws = new class extends \App\Support\AwsDatabaseService
    {
        public function access(): array
        {
            return ['host' => '13.38.206.137', 'user' => 'ubuntu', 'key' => 'test-key'];
        }

        public function deleteKey(string $path): void {}
    };

    return new class($aws, $backupFails, $importFails, $exists) extends \App\Support\DatabaseReplicationService
    {
        public array $steps = [];

        public function __construct(\App\Support\AwsDatabaseService $aws, public bool $backupFails, public bool $importFails, public bool $exists)
        {
            parent::__construct($aws);
        }

        protected function databaseExists(string $mode, array $access): bool
        {
            return $this->exists;
        }

        public function backup(string $mode, ?array $access = null): array
        {
            $this->steps[] = 'backup:'.$mode;

            if ($this->backupFails) {
                throw new RuntimeException('Backup falhou.');
            }

            return ['disk' => 'local', 'path' => 'backups/database/recovery.sql.gz'];
        }

        protected function export(string $mode, string $path, bool $ignoreTransientTables, ?array $access, bool $complete = false): void
        {
            $this->steps[] = 'export:'.$mode;
            file_put_contents($path, gzencode("CREATE TABLE test (id INT);\n"));
        }

        protected function import(string $mode, string $path, array $access): void
        {
            $this->steps[] = 'import:'.$mode;

            if ($this->importFails) {
                throw new RuntimeException('Importacao falhou.');
            }
        }
    };
}

beforeEach(function (): void {
    config(['database-management.local' => true]);

    foreach (['sandbox' => 3306, 'production' => 13306] as $mode => $port) {
        config(['database.profiles.'.$mode => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => $port,
            'database' => 'zentrumtask', 'username' => 'root', 'password' => 'private-password']]);
    }
});

it('backs up the destination before copying in either direction', function (string $source, string $target): void {
    $service = replicationTestService();
    $checkpoint = null;
    $result = $service->replicate($source, $target, function (array $backup) use (&$checkpoint): void {
        $checkpoint = $backup;
    });

    expect($result->successful)->toBeTrue()
        ->and($service->steps)->toBe(['backup:'.$target, 'export:'.$source, 'import:'.$target])
        ->and($checkpoint)->toBe($result->backup);
})->with([['production', 'sandbox'], ['sandbox', 'production']]);

it('never imports when the mandatory recovery backup fails', function (): void {
    $service = replicationTestService(backupFails: true);
    $result = $service->replicate('sandbox', 'production');
    expect($result->successful)->toBeFalse()->and($service->steps)->toBe(['backup:production']);
});

it('keeps the recovery backup reference when importing fails', function (): void {
    $service = replicationTestService(importFails: true);
    $result = $service->replicate('sandbox', 'production');
    expect($result->successful)->toBeFalse()->and($result->backup['path'])->toBe('backups/database/recovery.sql.gz');
});

it('supports a destination that does not exist yet without copying operational rows', function (): void {
    $service = replicationTestService(exists: false);
    $result = $service->replicate('production', 'sandbox');
    expect($result->successful)->toBeTrue()->and($service->steps)->toBe(['export:production', 'import:sandbox']);
    $profile = config('database.profiles.production');
    $process = invokeReplicationMethod($service, 'buildDumpProcess', $profile, 'mysqldump', true);
    expect($process->getCommandLine())->toContain('--ignore-table=zentrumtask.jobs')->toContain('--ignore-table=zentrumtask.sessions');
});

it('rejects equal endpoints and invalid modes before touching data', function (): void {
    $service = replicationTestService();
    expect($service->replicate('production', 'production')->successful)->toBeFalse()
        ->and($service->replicate('unknown', 'sandbox')->successful)->toBeFalse();
    config(['database.profiles.production' => config('database.profiles.sandbox')]);
    expect($service->replicate('production', 'sandbox')->successful)->toBeFalse()
        ->and($service->steps)->toBe([]);
});

it('restores AWS services through an exit trap and preserves preexisting maintenance state', function (): void {
    $script = invokeReplicationMethod(replicationTestService(), 'productionImportScript', 'mysql < input.sql');
    expect($script)->toContain('trap restore EXIT')->toContain('docker start')->toContain('docker stop --time=180')
        ->toContain('test -f storage/framework/down')->toContain('flock -n 9')->toContain('sleep 125');
});

it('streams compressed exports and strips the MariaDB header before import', function (): void {
    $service = new class extends DatabaseReplicationService
    {
        protected function resolveDumpBinary(string $driver): string
        {
            return PHP_BINARY;
        }

        protected function buildDumpProcess(array $configuration, string $binary, bool $ignoreTransientTables): Process
        {
            return new Process([PHP_BINARY, '-r', 'echo "/*M!999999\\\\- enable the sandbox mode */\n"; for ($i = 0; $i < 10000; $i++) { echo "INSERT INTO test VALUES (1);\n"; }']);
        }
    };
    $path = tempnam(sys_get_temp_dir(), 'zentrum-stream-test-');
    $import = null;

    try {
        invokeReplicationMethod($service, 'export', 'sandbox', $path, false, null, true);
        $import = invokeReplicationMethod($service, 'prepareImport', $path);
        $stream = fopen($import, 'rb');
        expect(fgets($stream))->toBe("INSERT INTO test VALUES (1);\n");
        fclose($stream);
        expect(filesize($import))->toBe(10000 * strlen("INSERT INTO test VALUES (1);\n"));
    } finally {
        unlink($path);

        if ($import !== null) {
            unlink($import);
        }
    }
});

it('redacts configured passwords from failure messages', function (): void {
    expect((new DatabaseReplicationService)->safeError(new RuntimeException('Failed private-password')))->toBe('Failed [oculto]');
});
