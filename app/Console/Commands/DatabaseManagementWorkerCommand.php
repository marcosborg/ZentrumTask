<?php

namespace App\Console\Commands;

use App\Support\DatabaseManagementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class DatabaseManagementWorkerCommand extends Command
{
    protected $signature = 'db:management-worker';

    protected $description = 'Process the independent database management queue';

    public function handle(DatabaseManagementService $management): int
    {
        $management->initialize();
        config(['queue.failed.driver' => 'database-uuids', 'queue.failed.database' => 'database_management']);
        app()->forgetInstance('queue.failer');

        try {
            return $management->withLock('worker', function () use ($management): int {
                try {
                    return Artisan::call('queue:work', ['connection' => 'database-management',
                        '--queue' => 'database-management', '--stop-when-empty' => true, '--tries' => 1,
                        '--timeout' => 7200, '--sleep' => 1, '--no-interaction' => true]);
                } finally {
                    $latest = $management->latest();

                    if (($latest['status'] ?? '') === 'running') {
                        $management->update($latest['id'], ['status' => 'failed',
                            'message' => 'O processo terminou sem confirmar a conclusao. Verifique os servicos e o backup antes de repetir.']);
                    }
                }
            }, 15);
        } catch (Throwable) {
            return self::FAILURE;
        }
    }
}
