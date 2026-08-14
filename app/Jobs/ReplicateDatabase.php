<?php

namespace App\Jobs;

use App\Support\DatabaseReplicationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReplicateDatabase implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 360;

    public int $tries = 1;

    public function __construct(
        public string $sourceMode,
        public string $targetMode,
    ) {}

    public function handle(DatabaseReplicationService $replication): void
    {
        $result = $replication->replicate($this->sourceMode, $this->targetMode);

        Log::log($result->successful ? 'info' : 'error', 'Database replication job finished', [
            'source' => $this->sourceMode,
            'target' => $this->targetMode,
            'successful' => $result->successful,
            'message' => $result->message,
        ]);
    }

    public function uniqueId(): string
    {
        return "{$this->sourceMode}:{$this->targetMode}";
    }
}
