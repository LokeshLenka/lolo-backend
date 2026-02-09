<?php

namespace App\Enums;

enum PayerType: string
{
    case PUBLIC = 'public';
    case INTERNAL = 'internal';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
