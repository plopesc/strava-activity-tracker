<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ActivityController extends AbstractController
{
    #[Route('/activities/{id}/detail', name: 'activity_detail')]
    public function detail(Activity $activity): Response
    {
        return $this->render('activity/detail.html.twig', [
            'activity' => $activity,
            'chartData' => $this->buildChartData($activity),
            'mapData' => $this->buildMapData($activity),
        ]);
    }

    /** @return null|list<array{float, float}> */
    private function buildMapData(Activity $activity): ?array
    {
        $streams = $activity->getRawStreams();
        $latlng = $streams['latlng']['data'] ?? null;

        if (!is_array($latlng) || $latlng === []) {
            return null;
        }

        return array_values($latlng);
    }

    /** @return null|array<string, mixed> */
    private function buildChartData(Activity $activity): ?array
    {
        $streams = $activity->getRawStreams();
        $velocityData = $streams['velocity_smooth']['data'] ?? null;

        if (!is_array($velocityData) || $velocityData === []) {
            return null;
        }

        $count = count($velocityData);

        // Distance x-axis in km: use stored stream or approximate linearly from total distance
        $distanceData = $streams['distance']['data'] ?? null;
        if (is_array($distanceData)) {
            $distanceKm = array_map(static fn ($d) => round((float) $d / 1000, 3), $distanceData);
        } else {
            $totalKm = ($activity->getDistance() ?? 0) / 1000;
            $step = $count > 1 ? $totalKm / ($count - 1) : 0;
            $distanceKm = array_map(static fn ($i) => round($i * $step, 3), range(0, $count - 1));
        }

        // Pace in min/km; null when stopped (velocity < 0.5 m/s)
        $paceData = array_map(static function ($v) {
            if (!is_numeric($v) || $v < 0.5) {
                return null;
            }

            return round(1000 / (float) $v / 60, 3);
        }, $velocityData);

        $heartrateData = isset($streams['heartrate']['data']) && is_array($streams['heartrate']['data'])
            ? array_values($streams['heartrate']['data'])
            : null;

        return [
            'distance' => array_values($distanceKm),
            'pace' => array_values($paceData),
            'heartrate' => $heartrateData,
        ];
    }
}
