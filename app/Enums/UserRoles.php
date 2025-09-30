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

    public static function RegistrableRoles(): array
    {
        return [
            self::ROLE_ADMIN->value,
            self::ROLE_MUSIC->value,
            self::ROLE_MANAGEMENT->value,
        ];
    }
    public static function RegistrableRolesWithoutAdmin(): array
    {
        return [
            // self::ROLE_ADMIN->value,
            self::ROLE_MUSIC->value,
            self::ROLE_MANAGEMENT->value,
        ];
    }
}
