<?php

namespace App\Enums;

enum EventType: string
{
    case Anyone = 'all';
    case ClubMembersOnly = 'club';
    case MusicMembersOnly = 'members';
}
