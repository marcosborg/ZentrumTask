<?php

namespace App\Enums;

enum VatRefundMode: string
{
    case None = 'none';
    case DriverDeliversVat = 'driver_delivers_vat';
}
