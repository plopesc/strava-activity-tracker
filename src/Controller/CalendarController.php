<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CalendarController extends AbstractController
{
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
}
