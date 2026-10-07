<?php

namespace App\Support;

use App\Jobs\ManageDatabaseOperation;
use Closure;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DatabaseManagementService
{
    public function directory(): string
    {
        $path = (string) config('database-management.directory');

        if (! is_dir($path) && ! mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new RuntimeException('Nao foi possivel preparar o armazenamento privado de gestao.');
        }

        return $path;
    }

    public function initialize(): void
    {
        $this->withLock('initialize', function (): void {
            $path = $this->directory().'/queue.sqlite';

            if (! is_file($path)) {
                touch($path);
                chmod($path, 0600);
            }

            config(['database.connections.database_management.database' => $path]);
            $schema = Schema::connection('database_management');

            if (! $schema->hasTable('jobs')) {
                $schema->create('jobs', function (Blueprint $table): void {
                    $table->bigIncrements('id');
                    $table->string('queue')->index();
                    $table->longText('payload');
                    $table->unsignedTinyInteger('attempts');
                    $table->unsignedInteger('reserved_at')->nullable();
                    $table->unsignedInteger('available_at');
                    $table->unsignedInteger('created_at');
                });
            }

            if (! $schema->hasTable('failed_jobs')) {
                $schema->create('failed_jobs', function (Blueprint $table): void {
                    $table->id();
                    $table->string('uuid')->unique();
                    $table->text('connection');
                    $table->text('queue');
                    $table->longText('payload');
                    $table->longText('exception');
                    $table->timestamp('failed_at')->useCurrent();
                });
            }
        });
    }

    /** @return array<string, mixed> */
    public function enqueue(string $type, string $source, ?string $target = null): array
    {
        if (! in_array($type, ['copy', 'backup', 'diagnose', 'optimize-aws'], true)) {
            throw new RuntimeException('Operacao invalida.');
        }

        if (! in_array($source, ['sandbox', 'production'], true)
            || ($type === 'copy' && (! in_array($target, ['sandbox', 'production'], true) || $source === $target))) {
            throw new RuntimeException('Perfis de origem e destino invalidos.');
        }

        if (! config('database-management.local') && ($type === 'copy' || $source !== 'production' || $type === 'optimize-aws')) {
            throw new RuntimeException('Esta operacao so esta disponivel no computador local.');
        }

        $this->initialize();
        $operation = $this->withLock('operation', function () use ($type, $source, $target): array {
            $this->assertIdle();
            $id = (string) Str::uuid();
            $operation = ['id' => $id, 'type' => $type, 'source' => $source, 'target' => $target,
                'status' => 'pending', 'message' => 'A aguardar o processo de gestao.', 'created_at' => now()->toIso8601String(),
                'updated_at' => time(), 'backup' => null];
            $this->write($id, $operation);
            $this->write('latest', $operation);

            return $operation;
        });

        try {
            Queue::connection('database-management')->push(new ManageDatabaseOperation($operation['id']), '', 'database-management');
            app(ManagedProcessLauncher::class)->launch(['db:management-worker', '--no-interaction'], $this->directory().'/worker-'.$operation['id'].'.log');
        } catch (Throwable $exception) {
            $this->update($operation['id'], ['status' => 'failed', 'message' => 'Nao foi possivel iniciar o processo de gestao.']);
            throw $exception;
        }

        return $operation;
    }

    public function assertIdle(): void
    {
        $latest = $this->latest();

        if (in_array($latest['status'] ?? '', ['pending', 'running'], true)) {
            throw new RuntimeException('Ja existe uma operacao de gestao pendente ou em execucao.');
        }
    }

    public function recoverInterruptedOperation(): void
    {
        $latest = $this->latest();

        if (! in_array($latest['status'] ?? '', ['pending', 'running'], true)
            || ($latest['updated_at'] ?? 0) > time() - 60) {
            return;
        }

        try {
            $this->withLock('worker', function () use ($latest): void {
                $this->withLock('operation', function () use ($latest): void {
                    $this->update($latest['id'], ['status' => 'failed',
                        'message' => 'O processo de gestao foi interrompido. Verifique os servicos e o backup antes de repetir.']);
                });
            });
        } catch (RuntimeException) {
        }
    }

    public function exclusive(Closure $callback): mixed
    {
        return $this->withLock('operation', function () use ($callback): mixed {
            $this->assertIdle();

            return $callback();
        });
    }

    public function execute(string $id): void
    {
        $this->withLock('operation', function () use ($id): void {
            $operation = $this->read($id);

            if (($operation['status'] ?? '') !== 'pending') {
                return;
            }

            $this->update($id, ['status' => 'running', 'message' => 'Operacao em execucao.']);
            $replication = app(DatabaseReplicationService::class);

            try {
                $backup = null;

                if ($operation['type'] === 'copy') {
                    $result = $replication->replicate($operation['source'], $operation['target'],
                        fn (array $backup) => $this->update($id, ['backup' => $backup, 'message' => 'Backup do destino guardado. A copiar dados.']));
                    $this->update($id, ['status' => $result->successful ? 'completed' : 'failed',
                        'message' => $result->message, 'backup' => $result->backup]);

                    return;
                }

                if ($operation['type'] === 'backup') {
                    $backup = $replication->backup($operation['source']);
                    $message = 'Backup privado criado e pronto para descarregar.';
                } elseif ($operation['type'] === 'diagnose') {
                    $message = app(AwsDatabaseService::class)->diagnose();
                } else {
                    app(AwsDatabaseService::class)->clearCaches();
                    $message = 'Caches dos servicos AWS limpas.';
                }

                $this->update($id, ['status' => 'completed', 'message' => $message, 'backup' => $backup]);
            } catch (Throwable $exception) {
                $this->update($id, ['status' => 'failed', 'message' => $replication->safeError($exception)]);
            }
        });
    }

    /** @return array<string, mixed>|null */
    public function latest(): ?array
    {
        return $this->read('latest');
    }

    /** @return array<string, mixed>|null */
    public function read(string $id): ?array
    {
        if (! in_array($id, ['latest', 'latest-backup', 'diagnostic'], true) && ! Str::isUuid($id)) {
            throw new RuntimeException('Identificador de operacao invalido.');
        }

        $path = $this->directory().'/'.$id.'.json';

        return is_file($path) ? json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : null;
    }

    /** @param array<string, mixed> $changes */
    public function update(string $id, array $changes): void
    {
        $operation = $this->read($id);

        if ($operation === null) {
            return;
        }

        $operation = [...$operation, ...$changes, 'updated_at' => time()];
        $this->write($id, $operation);

        if (is_array($operation['backup'] ?? null)) {
            $this->write('latest-backup', $operation);
        }

        if (($operation['type'] ?? '') === 'diagnose') {
            $this->write('diagnostic', $operation);
        }

        if (($this->latest()['id'] ?? null) === $id) {
            $this->write('latest', $operation);
        }
    }

    /** @param array<string, mixed> $value */
    protected function write(string $name, array $value): void
    {
        $path = $this->directory().'/'.$name.'.json';
        $temporary = $path.'.'.Str::random(12).'.tmp';

        if (file_put_contents($temporary, json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), LOCK_EX) === false
            || ! rename($temporary, $path)) {
            throw new RuntimeException('Nao foi possivel guardar o estado da operacao.');
        }
    }

    public function withLock(string $name, Closure $callback, int $waitSeconds = 0): mixed
    {
        $lock = fopen($this->directory().'/'.$name.'.lock', 'c');
        $deadline = microtime(true) + $waitSeconds;
        $acquired = false;

        if (is_resource($lock)) {
            do {
                $acquired = flock($lock, LOCK_EX | LOCK_NB);

                if (! $acquired && microtime(true) < $deadline) {
                    usleep(100000);
                }
            } while (! $acquired && microtime(true) < $deadline);
        }

        if (! $acquired) {
            if (is_resource($lock)) {
                fclose($lock);
            }

            throw new RuntimeException('Ja existe um processo de gestao em execucao.');
        }

        try {
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
