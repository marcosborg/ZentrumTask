<?php

namespace App\Jobs;

use App\Support\DatabaseManagementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ManageDatabaseOperation implements ShouldQueue
{
    use Queueable;

    public int $timeout = 7200;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public string $operationId)
    {
        $this->onConnection('database-management')->onQueue('database-management');
    }

    public function handle(DatabaseManagementService $management): void
    {
        $management->execute($this->operationId);
    }

    public function failed(?Throwable $exception): void
    {
        app(DatabaseManagementService::class)->update($this->operationId, [
            'status' => 'failed',
            'message' => 'O processo terminou antes de concluir. Verifique os servicos e o backup do destino antes de repetir.',
        ]);
    }
}
