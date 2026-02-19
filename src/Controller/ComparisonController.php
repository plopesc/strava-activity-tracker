<?php

declare(strict_types=1);

namespace App\Controller;

use App\Pattern\PatternRecognizer;
use App\Pattern\SegmentType;
use App\Repository\ActivityRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ComparisonController extends AbstractController
{
    #[Route('/activities/compare', name: 'activity_compare')]
    public function compare(
        Request $request,
        ActivityRepository $repo,
        PatternRecognizer $recognizer,
    ): Response {
        $ids = array_map('intval', $request->query->all('ids'));

        // Validation: 2–5 IDs
        if (count($ids) < 2 || count($ids) > 5) {
            $this->addFlash('error', 'Please select between 2 and 5 activities to compare.');

            return $this->redirectToRoute('activity_list');
        }

        // Load activities
        $activities = $repo->findBy(['id' => $ids]);
        if (count($activities) !== count($ids)) {
            $this->addFlash('error', 'One or more selected activities could not be found.');

            return $this->redirectToRoute('activity_list');
        }

        // Validate same pattern
        $signatures = array_unique(array_map(static fn ($a) => $a->getPatternSignature(), $activities));
        if (count($signatures) > 1) {
            $this->addFlash('error', 'Selected activities must share the same pattern.');

            return $this->redirectToRoute('activity_list');
        }

        $signature = $activities[0]->getPatternSignature();
        $selectedIds = array_map(static fn ($a) => $a->getId(), $activities);

        // ── Panel 1: Segment paces ──
        // Derive per-segment pace from patternSegments + rawLaps (or activity average as fallback)
        $segmentLabels = [];
        $segmentPaceDatasets = [];
        $colours = ['#4e79a7', '#f28e2b', '#e15759', '#76b7b2', '#59a14f'];

        // Determine segment labels from first activity that has segments
        $referenceSegments = null;
        foreach ($activities as $act) {
            if ($act->getPatternSegments() !== null) {
                $referenceSegments = $act->getPatternSegments();

                break;
            }
        }

        if ($referenceSegments !== null) {
            // Filter to training segments only for label building
            $trainingSegs = array_filter($referenceSegments, static fn ($s) => $s->type === SegmentType::Fast || $s->type === SegmentType::Recovery);
            foreach ($trainingSegs as $seg) {
                $label = $seg->count > 1
                    ? $seg->count . '× ' . $seg->type->value
                    : $seg->type->value;
                $segmentLabels[] = $label;
            }

            foreach ($activities as $i => $act) {
                $paces = [];
                $actSegs = $act->getPatternSegments() ?? [];
                $actTraining = array_filter($actSegs, static fn ($s) => $s->type === SegmentType::Fast || $s->type === SegmentType::Recovery);
                $actTraining = array_values($actTraining);

                // Try to get per-segment avg speed from segments (if stored)
                // or fall back to computing from rawLaps matched by type
                foreach ($actTraining as $j => $seg) {
                    $speedMs = $seg->avgSpeed;
                    if ($speedMs === null) {
                        // Fallback: use overall average speed
                        $speedMs = $act->getAverageSpeed();
                    }
                    $paceSecPerKm = $speedMs > 0 ? 1000 / $speedMs : 0;
                    $paces[] = round($paceSecPerKm / 60, 2); // pace in decimal minutes
                }

                $segmentPaceDatasets[] = [
                    'label' => $act->getActivityDate()?->format('Y-m-d') . ' ' . $act->getName(),
                    'data' => $paces,
                    'backgroundColor' => $colours[$i % count($colours)],
                ];
            }
        } else {
            // No segment data: use single whole-activity pace bar
            $segmentLabels = ['Overall'];
            foreach ($activities as $i => $act) {
                $pace = $act->getAverageSpeed() > 0 ? round((1000 / $act->getAverageSpeed()) / 60, 2) : 0;
                $segmentPaceDatasets[] = [
                    'label' => $act->getActivityDate()?->format('Y-m-d') . ' ' . $act->getName(),
                    'data' => [$pace],
                    'backgroundColor' => $colours[$i % count($colours)],
                ];
            }
        }

        // ── Panel 2: HR per segment ──
        $hrAvailable = true;
        $segmentHrDatasets = [];

        foreach ($activities as $act) {
            if ($act->getAverageHeartrate() === null) {
                $hrAvailable = false;

                break;
            }
        }

        if ($hrAvailable && $referenceSegments !== null) {
            foreach ($activities as $i => $act) {
                $hrs = [];
                $actSegs = $act->getPatternSegments() ?? [];
                $actTraining = array_values(array_filter($actSegs, static fn ($s) => $s->type === SegmentType::Fast || $s->type === SegmentType::Recovery));

                foreach ($actTraining as $seg) {
                    $hrs[] = $seg->avgHeartrate ?? round($act->getAverageHeartrate() ?? 0.0);
                }

                $segmentHrDatasets[] = [
                    'label' => $act->getActivityDate()?->format('Y-m-d'),
                    'data' => $hrs,
                    'backgroundColor' => $colours[$i % count($colours)],
                ];
            }
        } elseif ($hrAvailable) {
            // Whole-activity HR fallback
            foreach ($activities as $i => $act) {
                $segmentHrDatasets[] = [
                    'label' => $act->getActivityDate()?->format('Y-m-d'),
                    'data' => [round($act->getAverageHeartrate() ?? 0.0)],
                    'backgroundColor' => $colours[$i % count($colours)],
                ];
            }
        }

        // ── Panel 4: Trend line ──
        // Load ALL activities with same signature, ordered by date
        $allSamePattern = $signature !== null
            ? $repo->findByPatternSignature($signature)
            : $activities;

        $trendData = [];
        foreach ($allSamePattern as $act) {
            $pace = $act->getAverageSpeed() > 0 ? round((1000 / $act->getAverageSpeed()) / 60, 2) : null;
            $trendData[] = [
                'x' => $act->getActivityDate()?->format('Y-m-d'),
                'y' => $pace,
                'id' => $act->getId(),
                'selected' => in_array($act->getId(), $selectedIds, true),
            ];
        }

        return $this->render('activity/comparison.html.twig', [
            'activities' => $activities,
            'signature' => $signature,
            'segmentLabels' => $segmentLabels,
            'segmentPaceDatasets' => $segmentPaceDatasets,
            'hrAvailable' => $hrAvailable,
            'segmentHrDatasets' => $segmentHrDatasets,
            'trendData' => $trendData,
            'colours' => $colours,
        ]);
    }
}
