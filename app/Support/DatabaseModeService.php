<?php

namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class DatabaseModeService
{
    public function __construct(protected DatabaseManagementService $management) {}

    public function switch(string $mode): void
    {
        if (! config('database-management.local')) {
            throw new RuntimeException('O servidor AWS permanece sempre em producao.');
        }

        if (! in_array($mode, ['sandbox', 'production'], true)) {
            throw new RuntimeException('Modo de base de dados invalido.');
        }

        $this->management->exclusive(function () use ($mode): void {
            $previous = (string) config('database.mode');
            app(DatabaseReplicationService::class)->probe($mode);
            $path = app()->environmentFilePath();
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException('Nao foi possivel ler o ficheiro .env.');
            }

            $updated = preg_match('/^DB_MODE=.*/m', $contents) === 1
                ? preg_replace('/^DB_MODE=.*/m', 'DB_MODE='.$mode, $contents)
                : rtrim($contents).PHP_EOL.'DB_MODE='.$mode.PHP_EOL;
            $cached = app()->configurationIsCached();
            $connection = (string) config('database.default');
            $previousConnection = config('database.connections.'.$connection);

            try {
                $this->persist($path, (string) $updated);
                config(['database.mode' => $mode]);
                $profile = config('database.profiles.'.$mode);
                config(['database.connections.'.$connection => [...config('database.connections.'.$connection), ...$profile, 'url' => null]]);
                DB::purge($connection);

                if ($this->refreshConfiguration($cached, $mode) !== 0) {
                    throw new RuntimeException('Nao foi possivel atualizar a configuracao.');
                }

                if (Artisan::call('queue:restart', ['--no-interaction' => true]) !== 0) {
                    throw new RuntimeException('Nao foi possivel reiniciar os workers locais.');
                }
            } catch (Throwable $exception) {
                $this->persist($path, $contents);
                config(['database.mode' => $previous]);
                config(['database.connections.'.$connection => $previousConnection]);
                DB::purge((string) config('database.default'));
                $this->refreshConfiguration($cached, $previous);
                throw $exception;
            }
        });
    }

    protected function refreshConfiguration(bool $cached, string $mode): int
    {
        if ($cached) {
            return app(ManagedProcessLauncher::class)->runArtisan(['config:cache', '--no-interaction'], ['DB_MODE' => $mode]);
        }

        return Artisan::call('config:clear', ['--no-interaction' => true]);
    }

    protected function persist(string $path, string $contents): void
    {
        $temporary = $path.'.database-mode.tmp';

        if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $path)) {
            throw new RuntimeException('Nao foi possivel atualizar o ficheiro .env.');
        }
    }
}
