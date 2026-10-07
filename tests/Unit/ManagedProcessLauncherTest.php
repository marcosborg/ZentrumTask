<?php

use App\Support\ManagedProcessLauncher;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

it('preserves the operating system environment when HTTP server variables are incomplete', function (): void {
    $originalServer = $_SERVER;
    $_SERVER = ['PATH' => (string) getenv('PATH')];
    $directory = storage_path('framework/testing/launcher test '.\Illuminate\Support\Str::uuid());
    File::makeDirectory($directory, 0700, true);
    config(['database-management.php_binary' => PHP_BINARY]);

    try {
        (new ManagedProcessLauncher)->launch(['--version', '--no-interaction'], $directory.'/version.log');
        $deadline = microtime(true) + 15;
        $output = '';

        do {
            usleep(100000);
            clearstatcache();
            $output = is_file($directory.'/version.log') ? (string) file_get_contents($directory.'/version.log') : '';
        } while (! str_contains($output, 'Laravel Framework') && microtime(true) < $deadline);

        expect($output)->toContain('Laravel Framework');
    } finally {
        $_SERVER = $originalServer;
        File::deleteDirectory($directory);
    }
});

it('allows explicit mode overrides while running artisan with an incomplete HTTP environment', function (): void {
    $originalServer = $_SERVER;
    $_SERVER = ['PATH' => (string) getenv('PATH')];
    config(['database-management.php_binary' => PHP_BINARY]);

    try {
        expect((new ManagedProcessLauncher)->runArtisan(['--version', '--no-interaction'], ['DB_MODE' => 'sandbox']))->toBe(0);
    } finally {
        $_SERVER = $originalServer;
    }
});
