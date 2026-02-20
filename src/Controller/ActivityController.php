<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Activity;
use App\Repository\ActivityRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
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
        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('n'));

        if ($month < 1) {
            $month = 12;
            --$year;
        }

        if ($month > 12) {
            $month = 1;
            ++$year;
        }

        $patternSignature = $request->query->get('pattern');
        $gear = $request->query->get('gear');

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

        return $this->render('activity/calendar.html.twig', [
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
        ]);
    }

    #[Route('/activities/{id}/detail', name: 'activity_detail')]
    public function detail(#[MapEntity(mapping: ['id' => 'stravaId'])] Activity $activity): Response
    {
        return $this->render('activity/_detail.html.twig', [
            'activity' => $activity,
        ]);
    }

    #[Route('/activities/pattern', name: 'activity_pattern_list')]
    public function patternList(ActivityRepository $activityRepository): Response
    {
        return $this->render('activity/pattern_list.html.twig', [
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

        return $this->render('activity/pattern_detail.html.twig', [
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
