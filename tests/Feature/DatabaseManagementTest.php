<?php

use App\Filament\Pages\DownloadDatabaseBackup;
use App\Jobs\ManageDatabaseOperation;
use App\Models\User;
use App\Support\AwsDatabaseService;
use App\Support\DatabaseManagementService;
use App\Support\DatabaseReplicationResult;
use App\Support\DatabaseReplicationService;
use App\Support\ManagedProcessLauncher;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->managementDirectory = storage_path('framework/testing/db-management-'.\Illuminate\Support\Str::uuid());
    config(['database-management.directory' => $this->managementDirectory, 'database-management.local' => true]);
    Filament::setCurrentPanel('admin');
    $this->actingAs(User::factory()->create());
    Queue::fake();
    $this->mock(ManagedProcessLauncher::class)->shouldReceive('launch')->andReturnNull();
});

afterEach(function (): void {
    DB::purge('database_management');
    File::deleteDirectory($this->managementDirectory);
});

it('queues copies on the independent management connection and shows pending status', function (): void {
    Livewire::test(DownloadDatabaseBackup::class)
        ->callAction('productionToSandbox')
        ->assertSee('Pendente');

    Queue::assertPushed(ManageDatabaseOperation::class, fn (ManageDatabaseOperation $job): bool => $job->connection === 'database-management' && $job->queue === 'database-management' && $job->tries === 1);
    expect(config('queue.connections.database-management.connection'))->toBe('database_management')
        ->and(config('queue.connections.database-management.retry_after'))->toBeGreaterThan(7200);
});

it('requires the exact production database name before replacing production', function (): void {
    Livewire::test(DownloadDatabaseBackup::class)
        ->callAction('sandboxToProduction', data: ['database' => 'wrong-name'])
        ->assertHasActionErrors(['database']);
    Queue::assertNothingPushed();

    Livewire::test(DownloadDatabaseBackup::class)
        ->callAction('sandboxToProduction', data: ['database' => config('database.profiles.production.database')])
        ->assertHasNoActionErrors();
    Queue::assertPushed(ManageDatabaseOperation::class);
});

it('hides and blocks local management operations on the AWS server', function (): void {
    config(['database-management.local' => false]);
    Livewire::test(DownloadDatabaseBackup::class)
        ->assertActionHidden('toggleMode')
        ->assertActionHidden('productionToSandbox')
        ->assertActionHidden('sandboxToProduction');
    expect(fn () => app(DatabaseManagementService::class)->enqueue('copy', 'sandbox', 'production'))
        ->toThrow(RuntimeException::class);
});

it('rejects concurrent operations and mode changes while an operation is pending', function (): void {
    $management = app(DatabaseManagementService::class);
    $management->enqueue('diagnose', 'production');
    expect(fn () => $management->enqueue('backup', 'production'))->toThrow(RuntimeException::class)
        ->and(fn () => $management->exclusive(fn (): bool => true))->toThrow(RuntimeException::class);
});

it('stores the final replication failure and keeps its recovery backup visible', function (): void {
    $backup = ['disk' => 'local', 'path' => 'backups/database/recovery.sql.gz'];
    $management = app(DatabaseManagementService::class);
    $operation = $management->enqueue('copy', 'production', 'sandbox');
    $this->mock(DatabaseReplicationService::class)->shouldReceive('replicate')->once()
        ->with('production', 'sandbox', \Mockery::type(Closure::class))
        ->andReturn(new DatabaseReplicationResult(false, 'Importacao interrompida.', 'Falha', $backup));
    $management->execute($operation['id']);

    expect($management->latest()['status'])->toBe('failed')
        ->and($management->latest()['backup'])->toBe($backup);
    Livewire::test(DownloadDatabaseBackup::class)->assertSee('Falhou')->assertSee('recovery.sql.gz');
});

it('marks successful diagnostics completed and does not rerun completed operations', function (): void {
    $management = app(DatabaseManagementService::class);
    $operation = $management->enqueue('diagnose', 'production');
    $this->mock(AwsDatabaseService::class)->shouldReceive('diagnose')->once()->andReturn('AWS validada.');
    $management->execute($operation['id']);
    $management->execute($operation['id']);
    expect($management->latest()['status'])->toBe('completed');
    Livewire::test(DownloadDatabaseBackup::class)->assertSee('Concluido')->assertSee('AWS validada.');
});

it('downloads completed private backups through an authenticated action', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('backups/database/test.sql.gz', gzencode('CREATE TABLE test (id INT);'));
    $management = app(DatabaseManagementService::class);
    $operation = $management->enqueue('backup', 'production');
    $management->update($operation['id'], ['status' => 'completed', 'backup' => ['disk' => 'local', 'path' => 'backups/database/test.sql.gz']]);
    Livewire::test(DownloadDatabaseBackup::class)->callAction('downloadBackup')->assertFileDownloaded('test.sql.gz');
});

it('requires panel authentication to access operation status', function (): void {
    auth()->logout();
    expect(DownloadDatabaseBackup::canAccess())->toBeFalse();
    $this->get('/admin/download-database-backup')->assertRedirect();
});

it('recovers abandoned operations without automatically repeating a destructive copy', function (): void {
    $management = app(DatabaseManagementService::class);
    $operation = $management->enqueue('copy', 'sandbox', 'production');
    $operation['updated_at'] = time() - 120;
    file_put_contents($this->managementDirectory.'/latest.json', json_encode($operation));
    $management->recoverInterruptedOperation();
    expect($management->latest()['status'])->toBe('failed');
    Queue::assertPushed(ManageDatabaseOperation::class, 1);
});

it('keeps the last private backup available after a later diagnostic', function (): void {
    $management = app(DatabaseManagementService::class);
    $operation = $management->enqueue('backup', 'production');
    $backup = ['disk' => 'local', 'path' => 'backups/database/previous.sql.gz'];
    $management->update($operation['id'], ['status' => 'completed', 'backup' => $backup]);
    $management->enqueue('diagnose', 'production');
    expect($management->read('latest-backup')['backup'])->toBe($backup);
    Livewire::test(DownloadDatabaseBackup::class)->assertActionVisible('downloadBackup')->assertSee('previous.sql.gz');
});

it('rejects unknown database profiles before enqueuing an operation', function (): void {
    expect(fn () => app(DatabaseManagementService::class)->enqueue('backup', 'unknown'))->toThrow(RuntimeException::class);
    Queue::assertNothingPushed();
});
