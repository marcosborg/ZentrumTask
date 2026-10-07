<?php

namespace App\Enums;

enum TvdeOperation: string
{
    case Rental = 'rental';
    case Slot = 'slot';

    public function label(): string
    {
        return $this === self::Slot ? 'TVDE SLOT' : 'TVDE Aluguer';
    }
}
