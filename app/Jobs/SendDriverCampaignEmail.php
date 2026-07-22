<?php

namespace App\Jobs;

use App\Mail\DriverCampaignMail;
use App\Models\DriverMessageDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendDriverCampaignEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $deliveryId) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $delivery = DriverMessageDelivery::query()
            ->with('campaign')
            ->findOrFail($this->deliveryId);

        if ($delivery->email_status !== 'pending' || blank($delivery->email_address)) {
            return;
        }

        try {
            Mail::to($delivery->email_address)->send(new DriverCampaignMail(
                $delivery->campaign->subject,
                $delivery->campaign->body,
            ));

            $delivery->update([
                'email_status' => 'sent',
                'email_sent_at' => now(),
                'email_error' => null,
            ]);
        } catch (Throwable $exception) {
            $delivery->update([
                'email_status' => 'failed',
                'email_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
        }
    }
}
