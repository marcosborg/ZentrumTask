<?php

namespace App\Console\Commands;

use App\Support\AwsDatabaseService;
use App\Support\DatabaseManagementService;
use App\Support\DatabaseReplicationService;
use Illuminate\Console\Command;
use Throwable;

class AwsDatabaseTunnelCommand extends Command
{
    protected $signature = 'db:aws-tunnel';

    protected $description = 'Maintain the private local AWS database SSH tunnel';

    public function handle(AwsDatabaseService $aws, DatabaseManagementService $management): int
    {
        if (! config('database-management.local')) {
            $this->error('O tunel so esta disponivel no computador local.');

            return self::FAILURE;
        }

        try {
            return $management->withLock('tunnel', function () use ($aws): int {
                $started = time();
                $state = ['port' => (int) config('database-management.tunnel_port'),
                    'instance' => config('database.replication.production_dump.lightsail_instance'),
                    'pid' => getmypid(), 'heartbeat' => time(), 'error' => null];
                $aws->recordTunnelState($state);
                $access = null;
                $process = null;

                try {
                    if ($aws->portOpen()) {
                        throw new \RuntimeException('A porta do tunel ja esta ocupada.');
                    }

                    $access = $aws->access();
                    $state['identity'] = $aws->execute($access, $aws->dockerScript($aws->credentialsScript()
                        .'mysql --protocol=TCP --host="$db_host" --port="$db_port" --user="$db_user" --batch --skip-column-names '
                        .'--execute="SELECT CONCAT(@@hostname, CHAR(58), @@server_id)"'));
                    $process = $aws->tunnelProcess($access);
                    $process->start();

                    while ($process->isRunning()) {
                        $state['heartbeat'] = time();
                        $aws->recordTunnelState($state);
                        $environment = (string) file_get_contents(app()->environmentFilePath());

                        if (time() - $started > 120 && preg_match('/^DB_MODE=production\s*$/m', $environment) !== 1) {
                            return self::SUCCESS;
                        }

                        sleep(2);
                    }

                    throw new \RuntimeException('A ligacao SSH foi interrompida. A proxima ligacao voltara a criar o tunel.');
                } catch (Throwable $exception) {
                    $state['error'] = app(DatabaseReplicationService::class)->safeError($exception);
                    $state['heartbeat'] = time();
                    $aws->recordTunnelState($state);
                    $this->error($state['error']);

                    return self::FAILURE;
                } finally {
                    $process?->stop(3);

                    if ($access !== null) {
                        $aws->deleteKey($access['key']);
                    }
                }
            });
        } catch (Throwable) {
            return self::FAILURE;
        }
    }
}
