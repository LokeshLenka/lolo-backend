<?php

namespace App\Enums;

enum ManagementCategories: string
{
    case EVENT_ORGANIZER = 'event_organizer';
    case EVENT_PLANNER = 'event_planner';
    case MARKETING_COORDINATOR = 'marketing_coordinator';
    case SOCIAL_MEDIA_HANDLER = 'social_media_handler';
    case VIDEO_EDITOR = 'video_editor';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
