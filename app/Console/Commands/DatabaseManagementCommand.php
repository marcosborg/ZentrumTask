<?php

namespace App\Console\Commands;

use App\Support\AwsDatabaseService;
use App\Support\DatabaseManagementService;
use App\Support\DatabaseReplicationService;
use Illuminate\Console\Command;
use Throwable;

class DatabaseManagementCommand extends Command
{
    protected $signature = 'db:aws {operation=diagnose : diagnose or backup} {--queued : Run using the independent management worker}';

    protected $description = 'Validate AWS database access or create a private production backup';

    public function handle(AwsDatabaseService $aws, DatabaseReplicationService $replication): int
    {
        try {
            if ($this->option('queued') && in_array($this->argument('operation'), ['diagnose', 'backup'], true)) {
                $operation = app(DatabaseManagementService::class)->enqueue((string) $this->argument('operation'), 'production');
                $this->info('Operacao pendente: '.$operation['id']);

                return self::SUCCESS;
            }

            if ($this->argument('operation') === 'diagnose') {
                $this->info($aws->diagnose());
            } elseif ($this->argument('operation') === 'backup') {
                $backup = $replication->backup('production');
                $this->info('Backup privado: '.$backup['disk'].':'.$backup['path']);
            } else {
                $this->error('Escolha diagnose ou backup.');

                return self::FAILURE;
            }
        } catch (Throwable $exception) {
            $this->error($replication->safeError($exception));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
