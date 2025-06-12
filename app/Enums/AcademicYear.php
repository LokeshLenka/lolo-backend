<?php

namespace App\Enums;

enum AcademicYear: string
{
    case First = 'first';
    case Second = 'second';
    case Third = 'third';
    case Fourth = 'fourth';
    case PassedOut = 'passedout';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
