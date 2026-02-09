<?php

namespace App\Enums;

enum PayableType: string
{
    case Music = 'music_event';
    case Management = 'management_event';
    case Public = 'public_event';
    case User = 'user_registration';

    /**
     * Returns a human-friendly label for UI/Display.
     */
    public function label(): string
    {
        return match ($this) {
            self::Music      => 'Music Event',
            self::Management => 'Management Event',
            self::Public     => 'Public Event',
            self::User       => 'User Registration',
        };
    }

    /**
     * Get all raw string values.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get an associative array for dropdowns/selects.
     */
    public static function options(): array
    {
        return array_combine(
            self::values(),
            array_map(fn($case) => $case->label(), self::cases())
        );
    }
}
