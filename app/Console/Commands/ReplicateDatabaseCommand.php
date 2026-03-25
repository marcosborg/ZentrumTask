<?php

namespace App\Console\Commands;

use App\Support\DatabaseReplicationService;
use Illuminate\Console\Command;

class ReplicateDatabaseCommand extends Command
{
    protected $signature = 'db:replicate
        {source : Perfil de origem (ex: production)}
        {target : Perfil de destino (ex: sandbox)}';

    protected $description = 'Replicate a configured database profile into another profile';

    public function handle(DatabaseReplicationService $replication): int
    {
        $source = (string) $this->argument('source');
        $target = (string) $this->argument('target');

        $this->line("A copiar dados de {$source} para {$target}...");

        $result = $replication->replicate($source, $target);

        if (! $result->successful) {
            $this->error($result->title.': '.$result->message);

            return self::FAILURE;
        }

        $this->info($result->message);

        return self::SUCCESS;
    }
}
