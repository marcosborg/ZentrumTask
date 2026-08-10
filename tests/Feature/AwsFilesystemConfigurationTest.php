<?php

afterEach(function (): void {
    putenv('PUBLIC_FILESYSTEM_DRIVER');
    unset(
        $_ENV['PUBLIC_FILESYSTEM_DRIVER'],
        $_SERVER['PUBLIC_FILESYSTEM_DRIVER'],
        $_ENV['MEDIA_URL'],
        $_SERVER['MEDIA_URL'],
        $_ENV['APP_URL'],
        $_SERVER['APP_URL'],
    );
});

it('falls back to the app url when the media url is blank', function () {
    $_ENV['MEDIA_URL'] = '';
    $_SERVER['MEDIA_URL'] = '';
    $_ENV['APP_URL'] = 'http://localhost';
    $_SERVER['APP_URL'] = 'http://localhost';

    $configuration = require config_path('filesystems.php');

    expect($configuration['disks']['public']['url'])->toBe('http://localhost/storage');
});

it('uses an explicit remote media url without adding the local storage prefix', function () {
    $_ENV['MEDIA_URL'] = 'https://media.example.com';
    $_SERVER['MEDIA_URL'] = 'https://media.example.com';

    $configuration = require config_path('filesystems.php');

    expect($configuration['disks']['public']['url'])->toBe('https://media.example.com');
});

it('keeps the public disk local by default', function () {
    $configuration = require config_path('filesystems.php');

    expect($configuration['disks']['public']['driver'])->toBe('local');
});

it('can use s3 for public media without static aws credentials', function () {
    putenv('PUBLIC_FILESYSTEM_DRIVER=s3');
    $_ENV['PUBLIC_FILESYSTEM_DRIVER'] = 's3';
    $_SERVER['PUBLIC_FILESYSTEM_DRIVER'] = 's3';

    $configuration = require config_path('filesystems.php');

    expect($configuration['disks']['public'])
        ->toMatchArray([
            'driver' => 's3',
            'key' => null,
            'secret' => null,
        ])
        ->not->toHaveKey('visibility');
});
