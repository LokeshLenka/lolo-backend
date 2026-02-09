<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case UPI = 'upi';
    case CARD = 'card';
    case NETBANKING = 'netbanking';
    case WALLET = 'wallet';
    case EMI = 'emi';
    case PAYLATER = 'paylater';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
