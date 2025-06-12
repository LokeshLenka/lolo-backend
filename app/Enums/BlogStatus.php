<?php

namespace App\Enums;

enum BlogStatus: string
{
    case Published = 'published';
    case Draft = 'draft';

    public static function values()
    {
        return array_column(self::cases(), 'value');
    }
}
