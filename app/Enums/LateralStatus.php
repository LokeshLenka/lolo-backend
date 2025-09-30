<?php

namespace App\Enums;

enum LateralStatus: int
{
    case YES = 1;
    case NO = 0;

    public static function values()
    {
        return array_column(self::cases(), 'value');
    }
}
