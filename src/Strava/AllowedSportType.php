<?php

namespace App\Strava;

enum AllowedSportType: string
{
    case Run = 'Run';
    case TrailRun = 'TrailRun';
    case VirtualRun = 'VirtualRun';
    case UltraMarathon = 'UltraMarathon';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
