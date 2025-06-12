<?php

namespace App\Enums;

enum UserRoles: string
{
    case ROLE_ADMIN = 'admin';
    case ROLE_MUSIC = 'music';
    case ROLE_MANAGEMENT = 'management';
    case ROLE_PUBLIC = 'public';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
