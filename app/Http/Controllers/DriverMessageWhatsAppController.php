<?php

namespace App\Http\Controllers;

use App\Models\DriverMessageDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DriverMessageWhatsAppController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, DriverMessageDelivery $delivery): RedirectResponse
    {
        abort_if(blank($delivery->phone_number), 404);

        $delivery->loadMissing('campaign');
        $delivery->update([
            'whatsapp_status' => 'sent',
            'whatsapp_sent_at' => now(),
            'whatsapp_sent_by_user_id' => $request->user()->id,
        ]);

        $phoneNumber = preg_replace('/\D+/', '', $delivery->phone_number) ?? '';

        if (str_starts_with($phoneNumber, '00')) {
            $phoneNumber = substr($phoneNumber, 2);
        }

        if (strlen($phoneNumber) === 9) {
            $phoneNumber = '351'.$phoneNumber;
        }

        return redirect()->away('https://wa.me/'.$phoneNumber.'?text='.rawurlencode($delivery->campaign->body));
    }
}
