<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DriverSettlementSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public array $payload,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $driverName = (string) ($this->payload['driver']['name'] ?? 'Motorista');
        $period = (string) ($this->payload['period_label'] ?? '');

        return new Envelope(
            subject: "Settlement semanal - {$driverName} ({$period})",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.driver-settlement-summary',
            with: [
                'payload' => $this->payload,
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
