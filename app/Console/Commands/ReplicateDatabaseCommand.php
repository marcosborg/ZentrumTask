<?php

namespace App\Console\Commands;

use App\Support\DatabaseManagementService;
use App\Support\DatabaseReplicationService;
use Illuminate\Console\Command;

class ReplicateDatabaseCommand extends Command
{
    protected $signature = 'db:replicate
        {source : Perfil de origem (ex: production)}
        {target : Perfil de destino (ex: sandbox)}
        {--confirm-database= : Nome exato da base de producao para autorizar a substituicao}';

    protected $description = 'Replicate a configured database profile into another profile';

    public function handle(DatabaseReplicationService $replication): int
    {
        $source = (string) $this->argument('source');
        $target = (string) $this->argument('target');

        if ($target === 'production' && $this->option('confirm-database') !== config('database.profiles.production.database')) {
            $this->error('Para substituir producao, indique --confirm-database com o nome exato da base.');

            return self::FAILURE;
        }

        $this->line("A copiar dados de {$source} para {$target}...");

        try {
            $result = app(DatabaseManagementService::class)->exclusive(fn () => $replication->replicate($source, $target));
        } catch (\Throwable $exception) {
            $this->error($replication->safeError($exception));

            return self::FAILURE;
        }

        if (! $result->successful) {
            $this->error($result->title.': '.$result->message);

            return self::FAILURE;
        }

        $this->info($result->message);

        return self::SUCCESS;
    }
}
