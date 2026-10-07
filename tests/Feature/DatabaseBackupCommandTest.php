<?php

use App\Support\DatabaseReplicationService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->backupTestDirectory = storage_path('framework/testing/backup-command-'.\Illuminate\Support\Str::uuid());
    config(['database-management.directory' => $this->backupTestDirectory, 'database-management.local' => true,
        'database.mode' => 'production']);
    Storage::fake('local');
});

afterEach(function (): void {
    File::deleteDirectory($this->backupTestDirectory);
});

it('stores streamed SQL or gzip backups while preserving command options', function (bool $compressed): void {
    $this->mock(DatabaseReplicationService::class)->shouldReceive('createDump')->once()->with('production', \Mockery::type('string'))
        ->andReturnUsing(function (string $mode, string $path): void {
            file_put_contents($path, gzencode("CREATE TABLE test (id INT);\n"));
        });
    $options = ['--disk' => 'local', '--path' => 'backups/database', '--filename' => 'test-backup'];

    if ($compressed) {
        $options['--compress'] = true;
    }

    $this->artisan('db:backup', $options)->assertSuccessful();
    $contents = Storage::disk('local')->get('backups/database/test-backup.'.($compressed ? 'sql.gz' : 'sql'));
    expect($compressed ? gzdecode($contents) : $contents)->toBe("CREATE TABLE test (id INT);\n");
})->with([false, true]);

it('requires persistent private storage for backups on AWS', function (): void {
    config(['database-management.local' => false]);
    $this->artisan('db:backup', ['--disk' => 'local'])->assertFailed();
    Storage::disk('local')->assertDirectoryEmpty('backups/database');
});

it('requires an explicit database name for command line production replacement', function (): void {
    $this->artisan('db:replicate', ['source' => 'sandbox', 'target' => 'production'])->assertFailed();
});
