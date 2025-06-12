<?php

namespace App\Enums;

enum PromotedRole: string
{
    case CREDIT_MANAGER = 'credit_manager';
    case EXECUTIVE_BODY_MEMBER = 'executive_body_member';
    case MEMBERSHIP_HEAD = 'membership_head';

    public function label(): string
    {
        return match ($this) {
            self::CREDIT_MANAGER => 'Credit Manager',
            self::EXECUTIVE_BODY_MEMBER => 'Executive Body Member',
            self::MEMBERSHIP_HEAD => 'Membership Head',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
    