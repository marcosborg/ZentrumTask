<?php

namespace App\Mail;

use App\Models\CandidateApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationPaymentInstructionsMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $payment
     * @param  array<string, mixed>  $reservationOffer
     */
    public function __construct(
        public CandidateApplication $application,
        public array $payment,
        public array $reservationOffer,
    ) {}

    public function envelope(): Envelope
    {
        $vehicleName = $this->application->vehicleType?->display_name ?: 'a sua viatura';

        return new Envelope(
            subject: "Reserva de viatura - dados de pagamento para {$vehicleName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-payment-instructions',
            with: [
                'application' => $this->application,
                'payment' => $this->payment,
                'reservationOffer' => $this->reservationOffer,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
