<?php

namespace App\Enums;

enum PaymentStatus: string
{

    case Success = 'success';
    case Failed = 'failed';
    case Pending = 'pendng';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
