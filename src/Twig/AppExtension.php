<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class AppExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('pace_format', [$this, 'formatPace']),
            new TwigFilter('duration_format', [$this, 'formatDuration']),
        ];
    }

    /** Convert m/s to "mm:ss min/km" string */
    public function formatPace(float $speedMs): string
    {
        if ($speedMs <= 0) {
            return '—';
        }
        $paceSecPerKm = 1000 / $speedMs;
        $minutes = (int) ($paceSecPerKm / 60);
        $seconds = (int) ($paceSecPerKm % 60);

        return sprintf('%d:%02d min/km', $minutes, $seconds);
    }

    /** Convert seconds to "H:mm:ss" or "mm:ss" */
    public function formatDuration(int $seconds): string
    {
        if ($seconds >= 3600) {
            return gmdate('H:i:s', $seconds);
        }

        return gmdate('i:s', $seconds);
    }
}
