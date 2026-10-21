<?php

namespace App\Console\Commands;

use App\Mail\VehicleDocumentAlertsSummaryMail;
use App\Models\User;
use App\Models\VehicleDocument;
use App\Models\VehicleDocumentAlert;
use App\Models\VehicleRevisionReminder;
use App\Services\VehicleRevisionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
    protected $description = 'Send daily alerts for vehicle documents and revisions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $lock = Cache::lock('vehicle-document-revision-summary', 3600);
        if (! $lock->get()) {
            return self::SUCCESS;
        }

        try {
            return $this->checkAlerts();
        } finally {
            $lock->release();
        }
    }

    private function checkAlerts(): int
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
        $revisions = app(VehicleRevisionService::class)->pending();
        if ($alerts->isEmpty() && $revisions->isEmpty()) {
            return;
        }

        $recipients = User::query()
            ->whereIn('name', ['Adriano Silve', 'Adriano Silva', 'Marcos Borges'])
            ->get()
            ->keyBy('name');

        $adriano = $recipients->get('Adriano Silve') ?? $recipients->get('Adriano Silva');

        if ($adriano !== null) {
            $this->sendSummary($adriano, $alerts, $revisions, $today);
        }

        $tvdeAlerts = $alerts->filter(
            fn (VehicleDocumentAlert $alert): bool => $alert->document->vehicle?->source === 'tvde'
        )->values();
        $marcos = $recipients->get('Marcos Borges');

        if ($marcos !== null) {
            $this->sendSummary($marcos, $tvdeAlerts, $revisions->filter(fn (array $revision): bool => $revision['vehicle']->source === 'tvde')->values(), $today);
        }
    }

    private function sendSummary(User $recipient, Collection $alerts, \Illuminate\Support\Collection $revisions, Carbon $today): void
    {
        $sentCycles = VehicleRevisionReminder::query()->where('user_id', $recipient->id)
            ->whereDate('sent_on', $today)->pluck('cycle');
        $revisions = $revisions->reject(fn (array $revision): bool => $sentCycles->contains($revision['cycle']))->values();

        if ($alerts->isEmpty() && $revisions->isEmpty()) {
            return;
        }

        Mail::to($recipient)->send(new VehicleDocumentAlertsSummaryMail($recipient, $alerts, $today, $revisions));

        foreach ($revisions as $revision) {
            VehicleRevisionReminder::query()->firstOrCreate([
                'vehicle_revision_id' => $revision['revision_id'],
                'user_id' => $recipient->id,
                'cycle' => $revision['cycle'],
                'sent_on' => $today->toDateString(),
            ]);
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
