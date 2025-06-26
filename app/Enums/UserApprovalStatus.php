<?php

namespace App\Enums;

enum UserApprovalStatus: string
{
    case PENDING =  'pending';
    case EBM_APPROVED =  'ebm_approved';
    case MEMBERSHIP_APPROVED = 'membership_approved';
    case ADMIN_APPROVED = 'admin_approved';
    case REJECTED =  'rejected';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
