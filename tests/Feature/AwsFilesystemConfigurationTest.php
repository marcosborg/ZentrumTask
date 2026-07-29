<?php

afterEach(function (): void {
    putenv('PUBLIC_FILESYSTEM_DRIVER');
    unset($_ENV['PUBLIC_FILESYSTEM_DRIVER'], $_SERVER['PUBLIC_FILESYSTEM_DRIVER']);
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
