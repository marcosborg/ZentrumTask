<?php

$phpBinary = env('DB_MANAGEMENT_PHP_BINARY');

if (! $phpBinary) {
    foreach ([PHP_BINARY, PHP_BINDIR.'/php'.(DIRECTORY_SEPARATOR === '\\' ? '.exe' : ''),
        dirname(PHP_BINARY, 3).'/php/php.exe'] as $candidate) {
        if (is_file($candidate) && preg_match('/^php(?:[0-9.]*)?(?:\.exe)?$/i', basename($candidate)) === 1) {
            $phpBinary = $candidate;
            break;
        }
    }
}

return [
    'local' => in_array(env('APP_ENV', 'production'), ['local', 'testing'], true),
    'directory' => storage_path('app/private/database-management'),
    'php_binary' => $phpBinary,
    'timeout' => 7200,
    'tunnel_port' => (int) env('DB_PORT_PRODUCTION', 13306),
    'worker_container' => env('DB_MANAGEMENT_WORKER_CONTAINER', 'zentrum-tvde-worker'),
    'scheduler_container' => env('DB_MANAGEMENT_SCHEDULER_CONTAINER', 'zentrum-tvde-scheduler'),
];
