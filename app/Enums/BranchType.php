<?php

namespace App\Enums;

enum BranchType: string
{
    case AIDS = 'aids';
    case AIML = 'aiml';
    case CIC = 'cic';
    case CIVIL = 'civil';
    case CSBS = 'csbs';
    case CSD = 'csd';
    case CSE = 'cse';
    case CSG = 'csg';
    case CSIT = 'csit';
    case ECE = 'ece';
    case EEE = 'eee';
    case IT = 'it';
    case MECH = 'mech';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
