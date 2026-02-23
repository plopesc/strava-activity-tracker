<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ActivityController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->redirectToRoute('activity_calendar');
    }

    #[Route('/activities', name: 'activity_calendar')]
    public function calendar(Request $request, ActivityRepository $activityRepository): Response
    {
        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');

        $year = $request->query->getInt('year', $currentYear);
        $month = $request->query->getInt('month', $currentMonth);

        if ($month < 1) {
            $month = 12;
            --$year;
        }

        if ($month > 12) {
            $month = 1;
            ++$year;
        }

        // Never allow navigating to future months
        if ($year > $currentYear || ($year === $currentYear && $month > $currentMonth)) {
            $year = $currentYear;
            $month = $currentMonth;
        }

        $patternSignature = $request->query->get('pattern') ?: null;
        $gear = $request->query->get('gear') ?: null;

        $activities = $activityRepository->findByMonth($year, $month, $patternSignature, $gear);
        $activitiesByDay = [];
        foreach ($activities as $activity) {
            $day = (int) $activity->getActivityDate()?->format('j');
            $activitiesByDay[$day][] = $activity;
        }

        $firstDayOfMonth = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $daysInMonth = (int) $firstDayOfMonth->format('t');
        // Day of week: Monday=1 ... Sunday=7
        $startWeekday = (int) $firstDayOfMonth->format('N');

        return $this->render('calendar/index.html.twig', [
            'year' => $year,
            'month' => $month,
            'daysInMonth' => $daysInMonth,
            'startWeekday' => $startWeekday,
            'activitiesByDay' => $activitiesByDay,
            'patterns' => $activityRepository->findDistinctPatternSignatures(),
            'gears' => $activityRepository->findDistinctGearNames(),
            'selectedPattern' => $patternSignature,
            'selectedGear' => $gear,
            'firstDayOfMonth' => $firstDayOfMonth,
            'isCurrentMonth' => ($year === $currentYear && $month === $currentMonth),
        ]);
    }

    #[Route('/activities/{id}/detail', name: 'activity_detail')]
    public function detail(Activity $activity): Response
    {
        return $this->render('activity/detail.html.twig', [
            'activity' => $activity,
            'chartData' => $this->buildChartData($activity),
            'mapData' => $this->buildMapData($activity),
        ]);
    }

    /** @return list<array{float, float}>|null */
    private function buildMapData(Activity $activity): ?array
    {
        $streams = $activity->getRawStreams();
        $latlng = $streams['latlng']['data'] ?? null;

        if (!is_array($latlng) || $latlng === []) {
            return null;
        }

        return array_values($latlng);
    }

    /** @return array<string, mixed>|null */
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

    #[Route('/activities/pattern', name: 'activity_pattern_list')]
    public function patternList(ActivityRepository $activityRepository): Response
    {
        return $this->render('pattern/list.html.twig', [
            'groups' => $activityRepository->findPatternGroupsWithRecentActivities(),
        ]);
    }

    #[Route('/activities/pattern/{signature}', name: 'activity_pattern_detail', requirements: ['signature' => '.+'])]
    public function patternDetail(string $signature, Request $request, ActivityRepository $activityRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $sort = (string) $request->query->get('sort', 'date');
        $direction = (string) $request->query->get('direction', 'DESC');

        $result = $activityRepository->findByPatternSignaturePaginated($signature, $page, 25, $sort, $direction);
        $totalPages = (int) ceil($result['total'] / 25);

        // Compute trend: pace delta from first to last across all activities (ordered by date ASC)
        $trendText = null;
        $allActivities = $activityRepository->findByPatternSignature($signature);
        if (count($allActivities) >= 2) {
            $first = $allActivities[0];
            $last = $allActivities[count($allActivities) - 1];
            $firstPace = $first->getAverageSpeed() > 0 ? 1000 / $first->getAverageSpeed() : 0;
            $lastPace = $last->getAverageSpeed() > 0 ? 1000 / $last->getAverageSpeed() : 0;
            $deltaSec = $firstPace - $lastPace; // positive = improved (faster = lower pace)
            $absDelta = abs((int) $deltaSec);
            $trendDirection = $deltaSec > 0 ? '↑ improved' : '↓ slower';
            $trendText = sprintf(
                '%s %d:%02d min/km over %d sessions',
                $trendDirection,
                (int) ($absDelta / 60),
                $absDelta % 60,
                count($allActivities)
            );
        }

        return $this->render('pattern/detail.html.twig', [
            'signature' => $signature,
            'activities' => $result['activities'],
            'total' => $result['total'],
            'page' => $page,
            'totalPages' => $totalPages,
            'sort' => $sort,
            'direction' => $direction,
            'trendText' => $trendText,
        ]);
    }
}
