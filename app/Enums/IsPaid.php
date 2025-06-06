<?php

namespace App\Enums;

enum IsPaid: string
{
    case Paid = 'paid';
    case NotPaid = 'not_paid';

    public static function values()
    {
        return array_column(self::cases(), 'value');
    }
}
