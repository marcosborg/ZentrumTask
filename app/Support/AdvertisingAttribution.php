<?php

namespace App\Support;

use Illuminate\Http\Request;

class AdvertisingAttribution
{
    public static function capture(Request $request): void
    {
        $utm = collect($request->only(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term']))
            ->filter(fn ($value): bool => is_string($value))
            ->map(fn (string $value): string => mb_substr($value, 0, 150));

        if ($utm->isNotEmpty()) {
            $request->session()->put('ads_utm', $utm->all());
        }
    }
}
