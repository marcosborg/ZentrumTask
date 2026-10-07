<?php

namespace App\Jobs;

use App\Support\DatabaseManagementService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ReplicateDatabase implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public string $sourceMode, public string $targetMode) {}

    public function handle(DatabaseManagementService $management): void
    {
        $management->enqueue('copy', $this->sourceMode, $this->targetMode);
    }

    public function uniqueId(): string
    {
        return "{$this->sourceMode}:{$this->targetMode}";
    }
}
