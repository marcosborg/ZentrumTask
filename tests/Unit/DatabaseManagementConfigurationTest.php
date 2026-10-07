<?php

use App\Models\User;
use App\Support\LocalAdminUserProvider;
use Tests\TestCase;

uses(TestCase::class);

function withDatabaseManagementEnvironment(array $values, Closure $callback): void
{
    $original = [];

    foreach ($values as $key => $value) {
        $original[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }

    try {
        $callback();
    } finally {
        foreach ($original as $key => [$environment, $server, $process]) {
            if ($environment === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $environment;
            }

            if ($server === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $server;
            }

            $process === false ? putenv($key) : putenv($key.'='.$process);
        }
    }
}

it('pins local sessions cache queues and authentication independently of the selected mode', function (): void {
    withDatabaseManagementEnvironment(['APP_ENV' => 'local', 'DB_MODE' => 'production', 'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database'], function (): void {
        $cache = require config_path('cache.php');
        $session = require config_path('session.php');
        $queue = require config_path('queue.php');
        $auth = require config_path('auth.php');
        expect($cache['default'])->toBe('file')->and($session['driver'])->toBe('file')
            ->and($queue['connections']['database']['connection'])->toBe('sandbox_operational')
            ->and($queue['connections']['database-management']['connection'])->toBe('database_management')
            ->and($queue['failed']['database'])->toBe('sandbox_operational')
            ->and($auth['providers']['users']['driver'])->toBe('local-admin');
    });

    $provider = new LocalAdminUserProvider(app('hash'), User::class);
    expect($provider->createModel()->getConnectionName())->toBe('sandbox_operational');
});

it('forces production on AWS even when a legacy sandbox mode is configured', function (): void {
    withDatabaseManagementEnvironment(['APP_ENV' => 'production', 'DB_MODE' => 'sandbox'], function (): void {
        $database = require config_path('database.php');
        $management = require config_path('database-management.php');
        expect($database['mode'])->toBe('production')->and($management['local'])->toBeFalse();
    });
});

it('uses the local tunnel address rather than a legacy production host or URL', function (): void {
    withDatabaseManagementEnvironment(['APP_ENV' => 'local', 'DB_MODE' => 'production', 'DB_HOST_PRODUCTION' => 'old-cpanel.invalid', 'DB_PORT_PRODUCTION' => '13306', 'DB_URL' => 'mysql://old-cpanel.invalid/database'], function (): void {
        $database = require config_path('database.php');
        expect($database['connections']['mysql']['host'])->toBe('127.0.0.1')
            ->and($database['connections']['mysql']['port'])->toBe('13306')
            ->and($database['connections']['mysql']['url'])->toBeNull();
    });
});

it('persists management data across deployments and enables private S3 backups', function (): void {
    $script = file_get_contents(base_path('scripts/deploy-lightsail.sh'));
    expect($script)->toContain('source=${DATABASE_MANAGEMENT_DIRECTORY},target=/var/www/html/storage/app/private/database-management')
        ->toContain('DB_BACKUP_DISK=s3')->toContain('DB_MODE=production');
});

it('finds the installed PHP executable even when the compiled PHP_BINDIR differs', function (): void {
    $configuration = require config_path('database-management.php');
    expect(is_file($configuration['php_binary']))->toBeTrue();
});
