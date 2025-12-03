<?php

namespace App\Enums;

enum StatementStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
}
