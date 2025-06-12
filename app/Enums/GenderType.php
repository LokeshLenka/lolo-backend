<?php

namespace App\Enums;

enum GenderType: string
{
    case Male = 'male';
    case Female = 'female';

    public static function values()
    {
        return array_column(self::cases(), 'value');
    }
}
