<?php

namespace App\Console\Commands;

use App\Models\VehicleDocument;
use App\Models\VehicleDocumentAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class CheckVehicleDocumentsExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-vehicle-documents-expiry';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create daily alerts for vehicle document expiry';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = Carbon::today();

        VehicleDocument::query()
            ->whereNotNull('expires_at')
            ->chunkById(200, function ($documents) use ($today): void {
                foreach ($documents as $document) {
                    $level = $this->resolveLevel($document->expires_at, $today);

                    if ($level === null) {
                        continue;
                    }

                    VehicleDocumentAlert::query()->firstOrCreate(
                        [
                            'vehicle_document_id' => $document->id,
                            'level' => $level,
                            'triggered_on' => $today->toDateString(),
                        ],
                        [
                            'message' => $this->buildMessage($document->title, $level),
                        ]
                    );
                }
            });

        return self::SUCCESS;
    }

    private function resolveLevel(Carbon $expiresAt, Carbon $today): ?string
    {
        $expiresAt = $expiresAt->copy()->startOfDay();

        if ($expiresAt->lt($today)) {
            return 'expired';
        }

        if ($expiresAt->lte($today->copy()->addDays(7))) {
            return 'expiring_7';
        }

        if ($expiresAt->lte($today->copy()->addDays(30))) {
            return 'expiring_30';
        }

        return null;
    }

    private function buildMessage(string $title, string $level): string
    {
        return match ($level) {
            'expired' => 'Documento expirado: '.$title,
            'expiring_7' => 'Documento a expirar em 7 dias: '.$title,
            'expiring_30' => 'Documento a expirar em 30 dias: '.$title,
            default => 'Documento com alerta: '.$title,
        };
    }
}
