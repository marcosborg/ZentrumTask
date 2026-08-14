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
        ->toHaveKey('USERPROFILE');
});
