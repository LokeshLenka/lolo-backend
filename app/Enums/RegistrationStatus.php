<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case PENDING = 'pending';
    case WAIT_LISTED = 'waitlisted';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
