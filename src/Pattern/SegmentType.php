<?php

declare(strict_types=1);

namespace App\Pattern;

enum SegmentType: string
{
    case Easy = 'easy';
    case Fast = 'fast';
    case Recovery = 'recovery';
    case Moderate = 'moderate';
    case Warmup = 'warmup';
    case Cooldown = 'cooldown';

    public function isTraining(): bool
    {
        return $this === self::Fast || $this === self::Moderate;
    }
}
