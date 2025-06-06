<?php

namespace App\Enums;

enum EventStatus: string
{
    case Upcoming = 'upcoming';
    case Ongoing = 'ongoing';
    case Completed = 'completed';
    
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
