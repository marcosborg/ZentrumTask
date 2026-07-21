<?php

namespace App\Mail;

use App\Models\Driver;
use App\Models\TeslaVehicle;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeslaTirePressureAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{status: string, pressures: array<string, float|null>, difference: float|null, problems: list<string>}  $assessment
     */
    public function __construct(
        public Driver $driver,
        public TeslaVehicle $vehicle,
        public array $assessment,
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Aviso de pressao dos pneus - {$this->vehicle->display_name}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.tesla-tire-pressure-alert',
            with: [
                'driver' => $this->driver,
                'vehicle' => $this->vehicle,
                'assessment' => $this->assessment,
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
