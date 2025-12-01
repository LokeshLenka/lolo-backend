<?php

namespace App\Enums;

enum ManagementLevel: string
{
    case Base = 'base';
    case Promoted = 'promoted';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
