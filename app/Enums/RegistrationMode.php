<?php

namespace App\Enums;

enum RegistrationMode: string
{
    case Online = 'online';
    case Offline = 'offline';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
