<?php

use Illuminate\Support\Str;

$databaseMode = env('DB_MODE', 'sandbox');

$databaseProfiles = [
    'sandbox' => [
        'driver' => env('DB_DRIVER_SANDBOX', env('DB_CONNECTION', 'mysql')),
        'host' => env('DB_HOST_SANDBOX', '127.0.0.1'),
        'port' => env('DB_PORT_SANDBOX', '3306'),
        'database' => env('DB_DATABASE_SANDBOX', 'laravel'),
        'username' => env('DB_USERNAME_SANDBOX', 'root'),
        'password' => env('DB_PASSWORD_SANDBOX', ''),
    ],
    'production' => [
        'driver' => env('DB_DRIVER_PRODUCTION', env('DB_CONNECTION', 'mysql')),
        'host' => env('DB_HOST_PRODUCTION', '127.0.0.1'),
        'port' => env('DB_PORT_PRODUCTION', '3306'),
        'database' => env('DB_DATABASE_PRODUCTION', 'laravel'),
        'username' => env('DB_USERNAME_PRODUCTION', 'root'),
        'password' => env('DB_PASSWORD_PRODUCTION', ''),
    ],
];

$currentProfile = $databaseProfiles[$databaseMode] ?? $databaseProfiles['sandbox'];

$databaseHost = $currentProfile['host'];
$databasePort = $currentProfile['port'];
$databaseName = $currentProfile['database'];
$databaseUsername = $currentProfile['username'];
$databasePassword = $currentProfile['password'];

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => $databaseHost,
            'port' => $databasePort,
            'database' => $databaseName,
            'username' => $databaseUsername,
            'password' => $databasePassword,
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => $databaseHost,
            'port' => $databasePort,
            'database' => $databaseName,
            'username' => $databaseUsername,
            'password' => $databasePassword,
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => $databaseHost,
            'port' => $databasePort,
            'database' => $databaseName,
            'username' => $databaseUsername,
            'password' => $databasePassword,
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => 'prefer',
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => $databaseHost,
            'port' => $databasePort,
            'database' => $databaseName,
            'username' => $databaseUsername,
            'password' => $databasePassword,
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

    'backup' => [
        'disk' => env('DB_BACKUP_DISK', 'local'),
        'path' => env('DB_BACKUP_PATH', 'backups/database'),
        'binary' => env('DB_BACKUP_BINARY', null),
    ],

    'restore' => [
        'binary' => env('DB_RESTORE_BINARY', null),
    ],

    'mode' => $databaseMode,

    'profiles' => $databaseProfiles,

];
