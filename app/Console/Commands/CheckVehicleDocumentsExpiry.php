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

        VehicleDocumentAlert::query()
            ->where('is_resolved', false)
            ->whereDoesntHave('document')
            ->update(['is_resolved' => true, 'resolved_at' => now()]);

        VehicleDocument::query()
            ->with('vehicle')
            ->chunkById(200, function ($documents) use ($createdAlerts, $today): void {
                foreach ($documents as $document) {
                    $expiresAt = $document->expires_at?->toDateString();
                    $level = $document->expires_at ? $this->resolveLevel($document->expires_at, $today) : null;

                    $obsoleteAlerts = $document->alerts()->where('is_resolved', false);

                    if ($level !== null) {
                        $obsoleteAlerts->where(function ($query) use ($expiresAt): void {
                            $query->whereNull('document_expires_at')->orWhereDate('document_expires_at', '!=', $expiresAt);
                        });
                    }

                    $obsoleteAlerts->update(['is_resolved' => true, 'resolved_at' => now()]);

                    if ($level === null || $document->alerts()
                        ->whereDate('document_expires_at', $expiresAt)
                        ->where('is_resolved', true)
                        ->exists()) {
                        continue;
                    }

                    $alert = VehicleDocumentAlert::query()->firstOrCreate(
                        [
                            'vehicle_document_id' => $document->id,
                            'document_expires_at' => $document->expires_at->copy()->startOfDay(),
                            'level' => $level,
                            'triggered_on' => $today->copy()->startOfDay(),
                        ],
                        [
                            'message' => $this->buildMessage($document->title, $level),
                        ]
                    );

                    if ($alert->wasRecentlyCreated && ! $alert->is_resolved) {
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

        if ($expiresAt->lte($today->copy()->addDays(60))) {
            return 'expiring_60';
        }

        return null;
    }

    private function buildMessage(string $title, string $level): string
    {
        return match ($level) {
            'expired' => 'Documento expirado: '.$title,
            'expiring_7' => 'Documento a expirar em 7 dias: '.$title,
            'expiring_60' => 'Documento a expirar em 60 dias: '.$title,
            default => 'Documento com alerta: '.$title,
        };
    }
}
