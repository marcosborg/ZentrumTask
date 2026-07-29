<?php

namespace App\Mail;

use App\Models\VehicleHandoverProcedure;
use App\Support\VehicleHandoverDefinition;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class VehicleHandoverProceduresMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, VehicleHandoverProcedure>  $procedures
     */
    public function __construct(
        public Collection $procedures,
    ) {}

    public function envelope(): Envelope
    {
        $first = $this->procedures->first();
        $driverName = $first?->driver?->name ?? data_get($first?->driver_snapshot, 'name', 'Motorista');
        $subject = $this->procedures->count() > 1
            ? "Autos de troca de viatura - {$driverName}"
            : 'Auto de '.(VehicleHandoverDefinition::typeLabels()[$first?->type] ?? 'viatura')." - {$driverName}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.vehicle-handover-procedures',
            with: [
                'procedures' => $this->procedures,
                'typeLabels' => VehicleHandoverDefinition::typeLabels(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return $this->procedures
            ->filter(fn (VehicleHandoverProcedure $procedure): bool => filled($procedure->pdf_path) && Storage::disk('public')->exists($procedure->pdf_path))
            ->map(fn (VehicleHandoverProcedure $procedure): Attachment => Attachment::fromStorageDisk('public', $procedure->pdf_path)
                ->as('auto-viatura-'.$procedure->id.'.pdf')
                ->withMime('application/pdf'))
            ->values()
            ->all();
    }
}
