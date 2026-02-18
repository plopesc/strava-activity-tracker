<?php
namespace App\Controller;

use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ActivityController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        return $this->redirectToRoute('activity_list');
    }

    #[Route('/activities', name: 'activity_list')]
    public function list(ActivityRepository $repo): Response
    {
        $grouped = $repo->findGroupedByPattern();
        return $this->render('activity/index.html.twig', ['grouped' => $grouped]);
    }

    #[Route('/activities/pattern/{signature}', name: 'activity_pattern_group', requirements: ['signature' => '.+'])]
    public function patternGroup(string $signature, ActivityRepository $repo): Response
    {
        $activities = $repo->findByPatternSignature($signature);

        // Compute trend: pace delta from first to last
        $trendText = null;
        if (count($activities) >= 2) {
            $first = $activities[0];
            $last = $activities[count($activities) - 1];
            $firstPace = $first->getAverageSpeed() > 0 ? 1000 / $first->getAverageSpeed() : 0;
            $lastPace  = $last->getAverageSpeed()  > 0 ? 1000 / $last->getAverageSpeed()  : 0;
            $deltaSec  = $firstPace - $lastPace; // positive = improved (faster = lower pace)
            $absDelta  = abs((int) $deltaSec);
            $direction = $deltaSec > 0 ? '↑ improved' : '↓ slower';
            $trendText = sprintf('%s %d:%02d min/km over %d sessions',
                $direction,
                (int) ($absDelta / 60),
                $absDelta % 60,
                count($activities)
            );
        }

        return $this->render('activity/pattern_group.html.twig', [
            'signature' => $signature,
            'activities' => $activities,
            'trendText' => $trendText,
        ]);
    }
}
