<?php

namespace App\Enums;

enum BranchType: string
{
    case AIDS = 'aids';
    case CSE = 'cse';
    case CSD = 'csd';
    case CSG = 'csg';
    case CIC = 'cic';
    case CIVIL = 'civil';
    case AIML = 'aiml';
    case CSBS = 'csbs';
    case IT = 'it';
    case ECE = 'ece';
    case MECH = 'mech';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
