<?php

use App\Services\BoltWeekService;

it('calculates monday to sunday week boundaries', function () {
    $service = new BoltWeekService;

    $result = $service->calculateWeek('2025-12-29');

    expect($result['week_start']->format('Y-m-d'))->toBe('2025-12-29')
        ->and($result['week_end']->format('Y-m-d'))->toBe('2026-01-04');
});
