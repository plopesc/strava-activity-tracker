<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PatternController extends AbstractController
{
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
