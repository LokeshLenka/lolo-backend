<?php

namespace App\Enums;

enum UserRoles: string  
{
    case ROLE_ADMIN = 'admin';
    case ROLE_MEMBER = 'member';
    case ROLE_EBM = 'ebm';
    case ROLE_MH = 'mh';
    case ROLE_EP = 'ep';
    case ROLE_EO = 'eo';
    case ROLE_CM = 'cm';
}
