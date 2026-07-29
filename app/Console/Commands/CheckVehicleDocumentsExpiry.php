<?php

namespace App\Console\Commands;

use App\Mail\VehicleDocumentAlertsSummaryMail;
use App\Models\User;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentAlert;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

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
        $createdAlerts = new Collection;

        VehicleDocument::query()
            ->with('vehicle')
            ->whereNotNull('expires_at')
            ->chunkById(200, function ($documents) use ($createdAlerts, $today): void {
                foreach ($documents as $document) {
                    $level = $this->resolveLevel($document->expires_at, $today);

                    if ($level === null) {
                        continue;
                    }

                    $alert = VehicleDocumentAlert::query()->firstOrCreate(
                        [
                            'vehicle_document_id' => $document->id,
                            'level' => $level,
                            'triggered_on' => $today->copy()->startOfDay(),
                        ],
                        [
                            'message' => $this->buildMessage($document->title, $level),
                        ]
                    );

                    if ($alert->wasRecentlyCreated) {
                        $alert->setRelation('document', $document);
                        $createdAlerts->push($alert);
                    }
                }
            });

        $this->sendDailySummaries($createdAlerts, $today);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, VehicleDocumentAlert>  $alerts
     */
    private function sendDailySummaries(Collection $alerts, Carbon $today): void
    {
        if ($alerts->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->whereIn('name', ['Adriano Silve', 'Adriano Silva', 'Marcos Borges'])
            ->get()
            ->keyBy('name');

        $adriano = $recipients->get('Adriano Silve') ?? $recipients->get('Adriano Silva');

        if ($adriano !== null) {
            Mail::to($adriano)->send(new VehicleDocumentAlertsSummaryMail($adriano, $alerts, $today));
        }

        $tvdeAlerts = $alerts->filter(
            fn (VehicleDocumentAlert $alert): bool => $alert->document->vehicle?->source === 'tvde'
        )->values();
        $marcos = $recipients->get('Marcos Borges');

        if ($marcos !== null && $tvdeAlerts->isNotEmpty()) {
            Mail::to($marcos)->send(new VehicleDocumentAlertsSummaryMail($marcos, $tvdeAlerts, $today));
        }
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
