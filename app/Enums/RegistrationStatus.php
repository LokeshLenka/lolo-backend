<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Waitlisted = 'waitlisted';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
