<?php

namespace App\Enums;

enum RegistrationStatus: string
{
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Waitlisted = 'waitlisted';
}
