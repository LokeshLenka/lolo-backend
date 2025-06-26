<?php

namespace App\Enums;

enum EventType: string
{
    case Public = 'public';
    case ClubMembersOnly = 'club';
    case MusicMembersOnly = 'music';

    public static function values()
    {
        return array_column(self::cases(), 'value');
    }
}
