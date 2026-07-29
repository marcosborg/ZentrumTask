<?php

namespace App\Mail;

use App\Models\User;
use App\Models\VehicleDocumentAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class VehicleDocumentAlertsSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, VehicleDocumentAlert>  $alerts
     */
    public function __construct(
        public User $recipient,
        public Collection $alerts,
        public Carbon $alertDate,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Resumo diario de alertas de documentos de viaturas',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.vehicle-document-alerts-summary',
            with: [
                'recipient' => $this->recipient,
                'alerts' => $this->alerts,
                'alertDate' => $this->alertDate,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
