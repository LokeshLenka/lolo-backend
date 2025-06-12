<?php

namespace App\Enums;

enum MusicCategories: string
{
    case DRUMMER = 'drummer';
    case FLUTIST = 'flutist';
    case GUITARIST = 'guitarist';
    case PIANIST = 'pianist';
    case VIOLINIST = 'violinist';
    case VOCALIST = 'vocalist';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
