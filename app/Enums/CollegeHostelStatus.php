<?php

namespace App\Enums;

enum CollegeHostelStatus: int
{
    case YES = 1;
    case NO = 0;

    public static function values()
    {
        return array_column(self::cases(), 'value');
    }
}
