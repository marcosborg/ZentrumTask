<?php

namespace App\Mail;

use App\Models\DriverSettlement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SlotSettlementSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public DriverSettlement $settlement)
    {
        if ($settlement->operation !== 'slot') {
            throw new \InvalidArgumentException('O extrato deve pertencer à operação SLOT.');
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Zentrum | Extrato SLOT '.$this->settlement->period_start->format('d/m/Y'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.slot-settlement-summary');
    }
}
