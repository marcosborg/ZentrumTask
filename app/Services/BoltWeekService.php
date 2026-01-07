<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class BoltWeekService
{
    /**
     * @param  \DateTimeInterface|string  $date
     * @return array{week_start: Carbon, week_end: Carbon}
     */
    public function calculateWeek($date): array
    {
        $carbon = $date instanceof Carbon ? $date->copy() : Carbon::parse($date);

        return [
            'week_start' => $carbon->copy()->startOfWeek(Carbon::MONDAY),
            'week_end' => $carbon->copy()->endOfWeek(Carbon::SUNDAY),
        ];
    }
}
